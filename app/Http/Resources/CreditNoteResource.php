<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'credit_note_number' => $this->credit_note_number,
            'order_number' => $this->order?->order_number,
            'amount_kes' => $this->amount_kes,
            'amount_kes_formatted' => $this->amount_kes_formatted,
            'vat_kes_formatted' => $this->vat_kes_formatted,
            'currency' => $this->currency,
            'pdf_path' => $this->pdf_path,
            'issued_at' => $this->issued_at?->toISOString(),
            'student_name' => $this->user?->name,
        ];
    }
}
