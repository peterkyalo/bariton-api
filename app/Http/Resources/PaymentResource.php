<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'gateway' => $this->gateway,
            'amount_kes' => $this->amount_kes,
            'amount_kes_formatted' => number_format($this->amount_kes / 100, 2),
            'status' => $this->status,
            'reference' => $this->reference,
            'checkout_request_id' => $this->checkout_request_id,
            'payer_phone' => $this->payer_phone,
            'payer_name' => $this->payer_name,
            'recorder' => $this->recorder ? [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'uuid' => $this->order->uuid,
                'order_number' => $this->order->order_number,
                'user_name' => $this->order->user?->name,
                'user_email' => $this->order->user?->email,
            ] : null,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
