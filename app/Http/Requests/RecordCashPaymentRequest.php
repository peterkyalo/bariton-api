<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dashboard.finance') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'amount_kes' => ['required', 'integer', 'min:1'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
