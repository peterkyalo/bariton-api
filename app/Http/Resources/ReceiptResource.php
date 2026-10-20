<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'receipt_number' => $this->receipt_number,
            'amount_kes' => $this->amount_kes,
            'amount_kes_formatted' => $this->amount_kes_formatted,
            'vat_kes' => $this->vat_kes,
            'vat_kes_formatted' => $this->vat_kes_formatted,
            'currency' => $this->currency,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toISOString(),
            'download_url' => url("/api/v1/student/receipts/{$this->uuid}/download"),
        ];
    }
}
