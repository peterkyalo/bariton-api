<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number,
            'order_item_id' => $this->order_item_id,
            'item_title' => $this->orderItem?->title ?? 'Full Order',
            'amount_kes' => $this->amount_kes,
            'amount_kes_formatted' => $this->amount_kes_formatted,
            'amount_usd_formatted' => $this->amount_usd_formatted,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status,
            'refund_reference' => $this->refund_reference,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'reviewer_name' => $this->reviewer?->name,
            'credit_note' => $this->creditNote ? new CreditNoteResource($this->creditNote) : null,
            'student' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
