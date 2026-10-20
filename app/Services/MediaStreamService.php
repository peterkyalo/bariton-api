<?php

namespace App\Services;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaStreamService
{
    /**
     * Generate a temporary signed route URL to stream or download private lesson media.
     */
    public function generateSignedStreamUrl(Lesson $lesson, int $expiresMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'media.lessons.stream',
            now()->addMinutes($expiresMinutes),
            ['uuid' => $lesson->uuid]
        );
    }

    /**
     * Generate a temporary signed route URL to stream student assignment submission media.
     */
    public function generateSignedSubmissionStreamUrl(string $submissionUuid, int $expiresMinutes = 120): string
    {
        return URL::temporarySignedRoute(
            'media.submissions.stream',
            now()->addMinutes($expiresMinutes),
            ['uuid' => $submissionUuid]
        );
    }

    /**
     * Stream any stored file with HTTP Range support.
     */
    public function streamPath(string $filePath, ?string $filename, Request $request, string $disk = 'local'): StreamedResponse|BinaryFileResponse
    {
        if (! $filePath || ! Storage::disk($disk)->exists($filePath)) {
            throw new NotFoundHttpException('Requested media file could not be found.');
        }

        $fullPath = Storage::disk($disk)->path($filePath);
        $fileSize = filesize($fullPath);
        $mimeType = Storage::disk($disk)->mimeType($filePath) ?: 'application/octet-stream';
        $safeName = $filename ?: basename($filePath);

        $range = $request->header('Range');

        if (! $range) {
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $fileSize,
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="'.$safeName.'"',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ];

            return response()->stream(function () use ($fullPath) {
                $stream = fopen($fullPath, 'rb');
                fpassthru($stream);
                fclose($stream);
            }, 200, $headers);
        }

        if (! preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
            return response('', 416, ['Content-Range' => "bytes */{$fileSize}"]);
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;

        if ($start > $end || $start >= $fileSize) {
            return response('', 416, ['Content-Range' => "bytes */{$fileSize}"]);
        }

        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return response()->stream(function () use ($fullPath, $start, $length) {
            $stream = fopen($fullPath, 'rb');
            fseek($stream, $start);

            $bufferSize = 1024 * 64;
            $bytesSent = 0;

            while (! feof($stream) && $bytesSent < $length && connection_status() === 0) {
                $readLength = min($bufferSize, $length - $bytesSent);
                $buffer = fread($stream, $readLength);
                echo $buffer;
                flush();
                $bytesSent += strlen($buffer);
            }

            fclose($stream);
        }, 206, $headers);
    }

    /**
     * Stream a lesson file with full support for HTTP Range requests (RFC 7233).
     * Necessary for smooth audio seeking and progressive buffering.
     */
    public function streamLessonFile(Lesson $lesson, Request $request): StreamedResponse|BinaryFileResponse
    {
        $disk = $lesson->file_disk ?: config('filesystems.private_disk', 'local');

        if (! $lesson->file_path || ! Storage::disk($disk)->exists($lesson->file_path)) {
            throw new NotFoundHttpException('Requested media file could not be found.');
        }

        $fullPath = Storage::disk($disk)->path($lesson->file_path);
        $fileSize = filesize($fullPath);
        $mimeType = $lesson->mime_type ?: Storage::disk($disk)->mimeType($lesson->file_path) ?: 'application/octet-stream';

        $range = $request->header('Range');

        if (! $range) {
            // Standard full file response
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) $fileSize,
                'Accept-Ranges' => 'bytes',
                'Content-Disposition' => 'inline; filename="'.($lesson->file_name ?: basename($lesson->file_path)).'"',
                'Cache-Control' => 'private, no-cache, must-revalidate',
            ];

            return response()->stream(function () use ($fullPath) {
                $stream = fopen($fullPath, 'rb');
                fpassthru($stream);
                fclose($stream);
            }, 200, $headers);
        }

        // Parse byte range (e.g., "bytes=0-1024" or "bytes=1024-")
        if (! preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
            return response('', 416, ['Content-Range' => "bytes */{$fileSize}"]);
        }

        $start = (int) $matches[1];
        $end = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;

        if ($start > $end || $start >= $fileSize) {
            return response('', 416, ['Content-Range' => "bytes */{$fileSize}"]);
        }

        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="'.($lesson->file_name ?: basename($lesson->file_path)).'"',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ];

        return response()->stream(function () use ($fullPath, $start, $length) {
            $stream = fopen($fullPath, 'rb');
            fseek($stream, $start);

            $bufferSize = 1024 * 64; // 64KB chunks
            $bytesSent = 0;

            while (! feof($stream) && $bytesSent < $length && connection_status() === 0) {
                $readLength = min($bufferSize, $length - $bytesSent);
                $buffer = fread($stream, $readLength);
                echo $buffer;
                flush();
                $bytesSent += strlen($buffer);
            }

            fclose($stream);
        }, 206, $headers);
    }
}
