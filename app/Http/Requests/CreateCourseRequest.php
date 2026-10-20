<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:courses,slug'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'bundle_discount_type' => ['required', 'in:none,percent,fixed_kes'],
            'bundle_discount_value' => ['required', 'integer', 'min:0'],
            'requires_sequential_levels' => ['nullable', 'boolean'],
            'instructor_ids' => ['nullable', 'array'],
            'instructor_ids.*' => ['exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $discountType = $this->input('bundle_discount_type', 'none');
            $discountValue = (int) $this->input('bundle_discount_value', 0);

            if ($discountType === 'percent' && ($discountValue <= 0 || $discountValue >= 100)) {
                $validator->errors()->add('bundle_discount_value', 'Percent discount must be between 1% and 99%.');
            }
        });
    }
}
