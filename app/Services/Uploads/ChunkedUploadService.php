<?php

namespace App\Services\Uploads;

use App\Models\ChunkedUpload;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChunkedUploadService
{
    protected string $disk = 'local';

    /**
     * Initiate a resumable chunked upload session.
     */
    public function initiate(User $user, array $params): ChunkedUpload
    {
        $filename = $params['filename'] ?? 'upload.bin';
        $fileSizeBytes = (int) ($params['file_size_bytes'] ?? 0);
        $chunkSizeBytes = (int) ($params['chunk_size_bytes'] ?? (5 * 1024 * 1024)); // 5MB default chunk
        $mimeType = $params['mime_type'] ?? 'application/octet-stream';

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validate max allowed upload size from settings
        $maxVideoMb = (int) Setting::getValue('max_video_upload_mb', 200);
        $maxAudioMb = (int) Setting::getValue('max_audio_upload_mb', 50);

        $isVideo = str_starts_with($mimeType, 'video/') || in_array($extension, ['mp4', 'webm', 'mov'], true);
        $maxAllowedBytes = ($isVideo ? $maxVideoMb : $maxAudioMb) * 1024 * 1024;

        if ($fileSizeBytes > $maxAllowedBytes) {
            $limitMb = $isVideo ? $maxVideoMb : $maxAudioMb;
            throw ValidationException::withMessages([
                'file_size' => ["The file exceeds the maximum allowed size of {$limitMb} MB."],
            ]);
        }

        $totalChunks = (int) max(1, ceil($fileSizeBytes / $chunkSizeBytes));

        return ChunkedUpload::create([
            'user_id' => $user->id,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSizeBytes,
            'chunk_size_bytes' => $chunkSizeBytes,
            'total_chunks' => $totalChunks,
            'chunks_completed' => [],
            'status' => 'uploading',
            'storage_disk' => $this->disk,
            'temp_directory' => 'chunks/'.(string) Str::uuid(),
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Store an individual chunk. Idempotent: re-uploading an existing chunk overwrites safely.
     */
    public function storeChunk(ChunkedUpload $upload, int $chunkIndex, UploadedFile|string $chunk): void
    {
        if ($chunkIndex < 0 || $chunkIndex >= $upload->total_chunks) {
            throw ValidationException::withMessages([
                'chunk_index' => ["Chunk index {$chunkIndex} is outside of valid range [0, {$upload->total_chunks})."],
            ]);
        }

        $chunkDir = $upload->temp_directory;
        $chunkFilename = "chunk_{$chunkIndex}.part";

        if ($chunk instanceof UploadedFile) {
            Storage::disk($this->disk)->putFileAs($chunkDir, $chunk, $chunkFilename);
        } else {
            Storage::disk($this->disk)->put("{$chunkDir}/{$chunkFilename}", $chunk);
        }

        $upload->recordChunk($chunkIndex);
    }

    /**
     * Retrieve current upload progress status for resumption.
     */
    public function getStatus(ChunkedUpload $upload): array
    {
        $completed = $upload->chunks_completed ?? [];
        $progress = $upload->total_chunks > 0
            ? round((count($completed) / $upload->total_chunks) * 100, 1)
            : 0;

        return [
            'uuid' => $upload->uuid,
            'original_filename' => $upload->original_filename,
            'file_size_bytes' => $upload->file_size_bytes,
            'chunk_size_bytes' => $upload->chunk_size_bytes,
            'total_chunks' => $upload->total_chunks,
            'chunks_completed' => $completed,
            'progress_percent' => $progress,
            'is_complete' => $upload->isComplete(),
            'status' => $upload->status,
        ];
    }

    /**
     * Assemble all chunks in sequence into the final file and clean up temporary parts.
     */
    public function assemble(ChunkedUpload $upload): string
    {
        if (! $upload->isComplete()) {
            $missing = array_diff(range(0, $upload->total_chunks - 1), $upload->chunks_completed ?? []);
            throw ValidationException::withMessages([
                'chunks' => ['Cannot assemble file: missing chunks: '.implode(', ', $missing)],
            ]);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $upload->original_filename);
        $finalRelativePath = "submissions/{$upload->user_id}/{$upload->uuid}_{$safeName}";

        $finalFullPath = Storage::disk($this->disk)->path($finalRelativePath);
        $finalDir = dirname($finalFullPath);

        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $out = fopen($finalFullPath, 'wb');
        if (! $out) {
            throw new \RuntimeException("Could not open destination file for writing: {$finalFullPath}");
        }

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunkRelativePath = "{$upload->temp_directory}/chunk_{$i}.part";
            $chunkFullPath = Storage::disk($this->disk)->path($chunkRelativePath);

            if (! file_exists($chunkFullPath)) {
                fclose($out);
                throw new \RuntimeException("Missing chunk file on disk during assembly: {$chunkRelativePath}");
            }

            $in = fopen($chunkFullPath, 'rb');
            if ($in) {
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        }

        fclose($out);

        // Delete temporary chunk directory
        Storage::disk($this->disk)->deleteDirectory($upload->temp_directory);

        $upload->update([
            'status' => 'completed',
            'final_path' => $finalRelativePath,
        ]);

        return $finalRelativePath;
    }
}
