<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.enroll') ?? false;
    }

    public function rules(): array
    {
        return [
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'position_seconds' => ['nullable', 'integer', 'min:0'],
            'mark_complete' => ['nullable', 'boolean'],
        ];
    }
}
