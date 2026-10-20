<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'is_expired' => $this->isExpired(),
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'pricing' => [
                'total_kes' => $this->total_kes,
                'total_kes_formatted' => $this->total_kes_formatted,
                'total_usd_cents' => $this->total_usd_cents,
                'total_usd_formatted' => $this->total_usd_formatted,
                'vat_kes' => $this->vat_kes,
                'vat_kes_formatted' => $this->vat_kes_formatted,
                'vat_rate_percent' => (float) $this->vat_rate_percent,
                'exchange_rate_used' => (float) $this->exchange_rate_used,
            ],
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'item_id' => $item->item_id,
                    'title' => $item->title,
                    'price_kes' => $item->price_kes,
                    'price_kes_formatted' => number_format($item->price_kes / 100, 2),
                    'vat_kes' => $item->vat_kes,
                    'meta' => $item->meta,
                ];
            }),
            'payment' => $this->successfulPayment ? new PaymentResource($this->successfulPayment) : ($this->payments->last() ? new PaymentResource($this->payments->last()) : null),
            'receipt' => $this->receipt ? new ReceiptResource($this->receipt) : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
