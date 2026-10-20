<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.enroll') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', 'in:module,course_bundle,bundle_upgrade,assessment_package,exam_retake_package,bootcamp_booking,tutoring_package'],
            'item_id' => ['required', 'integer'],
        ];
    }
}
