<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutoringPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'session_count' => (int) $this->session_count,
            'price_kes' => (int) $this->price_kes,
            'price_usd_cents' => (int) ($this->price_usd_cents ?? 0),
            'validity_days' => (int) $this->validity_days,
            'is_active' => (bool) $this->is_active,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ] : null,
            'instructor' => $this->instructor ? [
                'id' => $this->instructor->id,
                'name' => $this->instructor->name,
                'email' => $this->instructor->email,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
