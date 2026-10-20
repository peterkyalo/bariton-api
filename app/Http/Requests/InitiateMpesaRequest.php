<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateMpesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.enroll') ?? false;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:15'],
        ];
    }
}
