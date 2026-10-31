<?php

namespace App\Http\Resources;

use App\Services\MediaStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = null;

        if ($this->file_path) {
            if ($this->kind === 'image' && $this->file_disk === 'public') {
                $url = Storage::disk('public')->url($this->file_path);
            } elseif (in_array($this->kind, ['pdf', 'audio'], true)) {
                $url = app(MediaStreamService::class)->generateSignedAttachmentUrl($this->resource, 60);
            }
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'kind' => $this->kind,
            'title' => $this->title,
            'youtube_video_id' => $this->youtube_video_id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'order' => $this->order,
            'url' => $url,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
