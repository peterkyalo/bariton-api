<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ChunkedUpload;
use App\Services\Uploads\ChunkedUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChunkedUploadController extends Controller
{
    public function __construct(
        protected ChunkedUploadService $uploadService
    ) {}

    /**
     * Initiate a new chunked upload session.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'file_size_bytes' => ['required', 'integer', 'min:1'],
            'chunk_size_bytes' => ['nullable', 'integer', 'min:1024'],
            'mime_type' => ['nullable', 'string', 'max:100'],
        ]);

        $upload = $this->uploadService->initiate($request->user(), $validated);

        return response()->json([
            'message' => 'Chunked upload initialized successfully.',
            'upload' => $this->uploadService->getStatus($upload),
        ], 201);
    }

    /**
     * Upload an individual chunk.
     */
    public function uploadChunk(Request $request, string $uuid): JsonResponse
    {
        $upload = ChunkedUpload::where('uuid', $uuid)->firstOrFail();

        if ($upload->user_id !== $request->user()->id && ! $request->user()->can('courses.teach')) {
            abort(403);
        }

        $validated = $request->validate([
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required'],
        ]);

        $chunkData = $request->file('chunk') ?: $request->input('chunk');
        $this->uploadService->storeChunk($upload, (int) $validated['chunk_index'], $chunkData);

        return response()->json([
            'message' => "Chunk {$validated['chunk_index']} received.",
            'upload' => $this->uploadService->getStatus($upload->fresh()),
        ]);
    }

    /**
     * Check upload status to support resuming after disconnection.
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        $upload = ChunkedUpload::where('uuid', $uuid)->firstOrFail();

        if ($upload->user_id !== $request->user()->id && ! $request->user()->can('courses.teach')) {
            abort(403);
        }

        return response()->json([
            'upload' => $this->uploadService->getStatus($upload),
        ]);
    }

    /**
     * Complete and assemble all uploaded chunks.
     */
    public function complete(Request $request, string $uuid): JsonResponse
    {
        $upload = ChunkedUpload::where('uuid', $uuid)->firstOrFail();

        if ($upload->user_id !== $request->user()->id && ! $request->user()->can('courses.teach')) {
            abort(403);
        }

        $finalPath = $this->uploadService->assemble($upload);

        return response()->json([
            'message' => 'Upload assembled and finalized successfully.',
            'upload_uuid' => $upload->uuid,
            'file_path' => $finalPath,
            'file_size_bytes' => $upload->file_size_bytes,
            'original_filename' => $upload->original_filename,
        ]);
    }
}
