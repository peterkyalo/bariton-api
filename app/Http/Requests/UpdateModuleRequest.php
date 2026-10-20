<?php

namespace App\Http\Requests;

use App\Models\Module;
use App\Services\PricingService;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('courses.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['sometimes', 'required', 'in:beginner,intermediate,advanced'],
            'order' => ['nullable', 'integer', 'min:1'],
            'price_kes' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('price_kes')) {
                return;
            }

            $module = $this->route('module');
            if (! ($module instanceof Module)) {
                $module = Module::with('course.modules')->find($module);
            }

            if (! $module || ! $module->course) {
                return;
            }

            $course = $module->course;
            $newPrice = (int) $this->input('price_kes', $module->price_kes);
            $simulatedTotal = $course->modules->where('id', '!=', $module->id)->sum('price_kes') + $newPrice;

            if ($simulatedTotal > 0 && $course->bundle_discount_type !== 'none' && $course->bundle_discount_value > 0) {
                try {
                    app(PricingService::class)->validateBundleDiscount(
                        $simulatedTotal,
                        $course->bundle_discount_type,
                        $course->bundle_discount_value
                    );
                } catch (InvalidArgumentException $e) {
                    $validator->errors()->add('price_kes', 'Updated module price violates course bundle discount: '.$e->getMessage());
                }
            }
        });
    }
}
