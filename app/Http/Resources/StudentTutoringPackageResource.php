<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentTutoringPackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'total_credits' => (int) $this->total_credits,
            'used_credits' => (int) $this->used_credits,
            'remaining_credits' => (int) $this->remaining_credits,
            'has_available_credits' => (bool) $this->hasAvailableCredits(),
            'expires_at' => $this->expires_at?->toISOString(),
            'is_expired' => (bool) $this->isExpired(),
            'status' => $this->status,
            'package' => new TutoringPackageResource($this->whenLoaded('tutoringPackage')),
            'instructor' => $this->instructor ? [
                'id' => $this->instructor->id,
                'name' => $this->instructor->name,
                'email' => $this->instructor->email,
            ] : null,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
