<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricingService = app(PricingService::class);
        $price = $pricingService->formatPrice($this->price_kes);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'level' => $this->level,
            'order' => $this->order,
            'price_kes' => $this->price_kes,
            'price_formatted' => $price['kes_formatted'],
            'price_usd_cents' => $price['usd_cents'],
            'price_usd_formatted' => $price['usd_formatted'],
            'lessons_count' => $this->lessons_count ?? $this->lessons->count(),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
