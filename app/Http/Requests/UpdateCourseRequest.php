<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Services\PricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.update') ?? false;
    }

    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');
        $courseId = is_numeric($course) ? $course : ($course instanceof Course ? $course->id : null);

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('courses', 'slug')->ignore($courseId)],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'bundle_discount_type' => ['sometimes', 'required', 'in:none,percent,fixed_kes'],
            'bundle_discount_value' => ['sometimes', 'required', 'integer', 'min:0'],
            'requires_sequential_levels' => ['nullable', 'boolean'],
            'is_open_for_validation' => ['nullable', 'boolean'],
            'validation_price_kes' => ['nullable', 'integer', 'min:0'],
            'validation_price_usd' => ['nullable', 'integer', 'min:0'],
            'instructor_ids' => ['nullable', 'array'],
            'instructor_ids.*' => ['exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $course = $this->route('course');
            if (! ($course instanceof Course)) {
                $course = Course::with('modules')->find($course);
            }

            if (! $course) {
                return;
            }

            $discountType = $this->input('bundle_discount_type', $course->bundle_discount_type);
            $discountValue = (int) $this->input('bundle_discount_value', $course->bundle_discount_value);

            $totalModulesPriceKes = $course->total_modules_price_kes;

            if ($totalModulesPriceKes > 0 && $discountType !== 'none') {
                try {
                    app(PricingService::class)->validateBundleDiscount(
                        $totalModulesPriceKes,
                        $discountType,
                        $discountValue
                    );
                } catch (InvalidArgumentException $e) {
                    $validator->errors()->add('bundle_discount_value', $e->getMessage());
                }
            }
        });
    }
}
