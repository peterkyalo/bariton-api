<?php

namespace App\Http\Resources;

use App\Services\MediaStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaService = app(MediaStreamService::class);

        $streamUrl = null;
        if (in_array($this->type, ['audio', 'pdf']) && $this->file_path) {
            $streamUrl = $mediaService->generateSignedStreamUrl($this->resource, 60);
        }

        // Rich text bodies are paid material: the public catalog only reveals them for free previews.
        $hideContent = $request->attributes->get('hide_lesson_content', false) && ! $this->is_preview;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'module_id' => $this->module_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'order' => $this->order,
            'type' => $this->type,
            'youtube_video_id' => $this->youtube_video_id,
            'content' => $hideContent ? null : $this->content,
            'duration_seconds' => $this->duration_seconds,
            'is_preview' => (bool) $this->is_preview,
            'file_name' => $this->file_name,
            'file_size_bytes' => $this->file_size_bytes,
            'mime_type' => $this->mime_type,
            'stream_url' => $streamUrl,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
