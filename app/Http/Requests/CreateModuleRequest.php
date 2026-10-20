<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Services\PricingService;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class CreateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
            'order' => ['nullable', 'integer', 'min:1'],
            'price_kes' => ['required', 'integer', 'min:0'],
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

            $newPrice = (int) $this->input('price_kes', 0);
            $simulatedTotal = $course->total_modules_price_kes + $newPrice;

            if ($simulatedTotal > 0 && $course->bundle_discount_type !== 'none' && $course->bundle_discount_value > 0) {
                try {
                    app(PricingService::class)->validateBundleDiscount(
                        $simulatedTotal,
                        $course->bundle_discount_type,
                        $course->bundle_discount_value
                    );
                } catch (InvalidArgumentException $e) {
                    $validator->errors()->add('price_kes', 'Adding module violates course bundle discount: '.$e->getMessage());
                }
            }
        });
    }
}
