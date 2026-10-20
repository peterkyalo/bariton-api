<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $secondsRemaining = null;
        if ($this->status === 'held' && $this->hold_expires_at) {
            $secondsRemaining = max(0, (int) now()->diffInSeconds($this->hold_expires_at, false));
        }

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'booking_type' => $this->booking_type,
            'training_fee_kes' => (int) $this->training_fee_kes,
            'accommodation_fee_kes' => (int) $this->accommodation_fee_kes,
            'total_fee_kes' => (int) $this->total_fee_kes,
            'status' => $this->status,
            'hold_type' => $this->hold_type,
            'hold_expires_at' => $this->hold_expires_at?->toISOString(),
            'seconds_remaining_on_hold' => $secondsRemaining,
            'is_hold_active' => (bool) $this->isHoldActive(),
            'is_hold_expired' => (bool) $this->isHoldExpired(),
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'unseated_at' => $this->unseated_at?->toISOString(),
            'unseated_reason' => $this->unseated_reason,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'event' => new EventResource($this->whenLoaded('event')),
            'order' => $this->order ? [
                'id' => $this->order->id,
                'uuid' => $this->order->uuid,
                'status' => $this->order->status,
                'total_kes' => (int) $this->order->total_kes,
                'currency' => $this->order->currency,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
