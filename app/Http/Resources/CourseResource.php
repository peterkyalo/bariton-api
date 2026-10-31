<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricingService = app(PricingService::class);

        $totalModulesPriceKes = $this->total_modules_price_kes;
        $bundlePriceKes = $this->bundle_price_kes;
        $bundleSavingsKes = $this->bundle_savings_kes;

        $totalPrice = $pricingService->formatPrice($totalModulesPriceKes);
        $bundlePrice = $pricingService->formatPrice($bundlePriceKes);
        $savingsPrice = $pricingService->formatPrice($bundleSavingsKes);

        $currentUser = $request->user('sanctum') ?? $request->user();
        $upgradeData = $pricingService->calculateUpgradePrice($this->resource, $currentUser);

        $validationPrice = $pricingService->formatPrice((int) ($this->validation_price_kes ?: 0));

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_url,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'status' => $this->status,
            'requires_sequential_levels' => (bool) $this->requires_sequential_levels,
            'is_open_for_validation' => (bool) $this->is_open_for_validation,
            'validation_pricing' => [
                'is_open_for_validation' => (bool) $this->is_open_for_validation,
                'price_kes' => (int) ($this->validation_price_kes ?: 0),
                'price_kes_formatted' => $validationPrice['kes_formatted'],
                'price_usd_cents' => $validationPrice['usd_cents'],
                'price_usd_formatted' => $validationPrice['usd_formatted'],
            ],
            'instructors' => $this->instructors->map(function ($instructor) {
                return [
                    'id' => $instructor->id,
                    'name' => $instructor->name,
                    'email' => $instructor->email,
                    'is_primary' => (bool) ($instructor->pivot->is_primary ?? false),
                ];
            }),
            'modules_count' => $this->modules_count ?? $this->modules->count(),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'pricing' => [
                'total_modules_kes' => $totalModulesPriceKes,
                'total_modules_formatted' => $totalPrice['kes_formatted'],
                'total_modules_usd_cents' => $totalPrice['usd_cents'],
                'total_modules_usd_formatted' => $totalPrice['usd_formatted'],
                'bundle_discount_type' => $this->bundle_discount_type,
                'bundle_discount_value' => $this->bundle_discount_value,
                'bundle_price_kes' => $bundlePriceKes,
                'bundle_price_formatted' => $bundlePrice['kes_formatted'],
                'bundle_price_usd_cents' => $bundlePrice['usd_cents'],
                'bundle_price_usd_formatted' => $bundlePrice['usd_formatted'],
                'bundle_savings_kes' => $bundleSavingsKes,
                'bundle_savings_formatted' => $savingsPrice['kes_formatted'],
                'bundle_savings_usd_cents' => $savingsPrice['usd_cents'],
                'bundle_savings_usd_formatted' => $savingsPrice['usd_formatted'],
            ],
            'upgrade_pricing' => [
                'is_enrolled' => $upgradeData['is_enrolled'] ?? false,
                'enrollment_uuid' => $upgradeData['enrollment_uuid'] ?? null,
                'enrollment_status' => $upgradeData['enrollment_status'] ?? null,
                'progress_percent' => $upgradeData['progress_percent'] ?? 0.0,
                'has_owned_modules' => $upgradeData['has_owned_modules'],
                'is_validation_path' => $upgradeData['is_validation_path'] ?? false,
                'owned_module_ids' => $upgradeData['owned_module_ids'],
                'total_paid_kes' => $upgradeData['total_paid_kes'],
                'upgrade_price_kes' => $upgradeData['upgrade_price']['kes'],
                'upgrade_price_formatted' => $upgradeData['upgrade_price']['kes_formatted'],
                'upgrade_price_usd_cents' => $upgradeData['upgrade_price']['usd_cents'],
                'upgrade_price_usd_formatted' => $upgradeData['upgrade_price']['usd_formatted'],
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
