<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventWaitlistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $secondsRemaining = null;
        if ($this->status === 'offered' && $this->offer_expires_at) {
            $secondsRemaining = max(0, (int) now()->diffInSeconds($this->offer_expires_at, false));
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'booking_type' => $this->booking_type,
            'status' => $this->status,
            'offered_at' => $this->offered_at?->toISOString(),
            'offer_expires_at' => $this->offer_expires_at?->toISOString(),
            'seconds_remaining_on_offer' => $secondsRemaining,
            'event' => new EventResource($this->whenLoaded('event')),
            'held_booking_id' => $this->held_booking_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
