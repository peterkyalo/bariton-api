<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'address' => $this->address,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'capacity' => (int) $this->capacity,
            'occupied_seats_count' => (int) $this->occupied_seats_count,
            'available_seats_count' => (int) $this->available_seats_count,
            'is_full' => (bool) $this->isFull(),
            'training_fee_kes' => (int) $this->training_fee_kes,
            'has_accommodation' => (bool) $this->has_accommodation,
            'accommodation_fee_kes' => (int) $this->accommodation_fee_kes,
            'total_fee_with_accommodation_kes' => (int) ($this->training_fee_kes + ($this->has_accommodation ? $this->accommodation_fee_kes : 0)),
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
