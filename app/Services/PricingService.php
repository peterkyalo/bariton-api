<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use InvalidArgumentException;

class PricingService
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    /**
     * Convert KES minor units to USD minor units (cents), rounded up to the next whole cent.
     * Formula: ceil((kesAmount / 100) / exchangeRate * 100) = ceil(kesAmount / exchangeRate).
     */
    public function convertKesToUsdCents(int $kesMinorUnits): int
    {
        if ($kesMinorUnits <= 0) {
            return 0;
        }

        $exchangeRate = (float) $this->settingsService->get('usd_exchange_rate', 130.00);

        if ($exchangeRate <= 0) {
            $exchangeRate = 130.00;
        }

        return (int) ceil($kesMinorUnits / $exchangeRate);
    }

    /**
     * Format a price object containing KES minor units, USD cents, and formatted strings.
     */
    public function formatPrice(int $kesMinorUnits): array
    {
        $usdCents = $this->convertKesToUsdCents($kesMinorUnits);

        return [
            'kes' => $kesMinorUnits,
            'kes_formatted' => number_format($kesMinorUnits / 100, 2),
            'usd_cents' => $usdCents,
            'usd_formatted' => number_format($usdCents / 100, 2),
        ];
    }

    /**
     * Calculate bundle price given a course or a total price with discount parameters.
     */
    public function calculateBundlePrice(int $totalModulesPriceKes, string $discountType, int $discountValue): int
    {
        if ($totalModulesPriceKes <= 0 || $discountType === 'none' || $discountValue <= 0) {
            return $totalModulesPriceKes;
        }

        if ($discountType === 'percent') {
            $discount = (int) round($totalModulesPriceKes * ($discountValue / 100));
            return max(0, $totalModulesPriceKes - $discount);
        }

        if ($discountType === 'fixed_kes') {
            return max(0, $totalModulesPriceKes - $discountValue);
        }

        return $totalModulesPriceKes;
    }

    /**
     * Validate that the bundle discount satisfies the platform rule:
     * "Reject any discount that would make the bundle price zero or higher than the sum of single modules."
     *
     * @throws InvalidArgumentException
     */
    public function validateBundleDiscount(int $totalModulesPriceKes, string $discountType, int $discountValue): void
    {
        if ($discountType === 'none' || $discountValue === 0) {
            return;
        }

        if ($totalModulesPriceKes <= 0) {
            // Cannot apply a discount when course has no modules / price is 0
            if ($discountValue > 0) {
                throw new InvalidArgumentException('Cannot apply a bundle discount to a course with no module pricing.');
            }
            return;
        }

        if ($discountType === 'percent') {
            if ($discountValue <= 0 || $discountValue >= 100) {
                throw new InvalidArgumentException('Percent bundle discount must be between 1% and 99%. A discount of 100% or more would reduce the bundle price to zero.');
            }
        } elseif ($discountType === 'fixed_kes') {
            if ($discountValue <= 0) {
                throw new InvalidArgumentException('Fixed KES discount must be greater than zero.');
            }
            if ($discountValue >= $totalModulesPriceKes) {
                throw new InvalidArgumentException('Fixed KES discount cannot equal or exceed total module price, as bundle price cannot be zero.');
            }
        } else {
            throw new InvalidArgumentException("Unsupported discount type: {$discountType}");
        }

        $bundlePrice = $this->calculateBundlePrice($totalModulesPriceKes, $discountType, $discountValue);

        if ($bundlePrice <= 0) {
            throw new InvalidArgumentException('Bundle discount is invalid: bundle price cannot be zero or negative.');
        }

        if ($bundlePrice >= $totalModulesPriceKes) {
            throw new InvalidArgumentException('Bundle discount is invalid: bundle price cannot be equal to or higher than the sum of individual modules.');
        }
    }

    /**
     * Calculate the upgrade price for a student on a specific course.
     * "A student who owns modules in a course pays the bundle price minus what they paid for those modules, never below zero."
     */
    public function calculateUpgradePrice(Course $course, ?User $user): array
    {
        $bundlePriceKes = $course->bundle_price_kes;

        if (! $user) {
            return [
                'has_owned_modules' => false,
                'owned_module_ids' => [],
                'total_paid_kes' => 0,
                'upgrade_price' => $this->formatPrice($bundlePriceKes),
            ];
        }

        $enrollment = $course->enrollments()
            ->where('user_id', $user->id)
            ->with('enrollmentModules')
            ->first();

        if (! $enrollment || $enrollment->enrollmentModules->isEmpty()) {
            return [
                'has_owned_modules' => false,
                'owned_module_ids' => [],
                'total_paid_kes' => 0,
                'upgrade_price' => $this->formatPrice($bundlePriceKes),
            ];
        }

        $ownedModuleIds = $enrollment->enrollmentModules->pluck('module_id')->toArray();
        $totalPaidKes = (int) $enrollment->enrollmentModules->sum('amount_paid_kes');
        $upgradePriceKes = max(0, $bundlePriceKes - $totalPaidKes);

        return [
            'has_owned_modules' => true,
            'owned_module_ids' => $ownedModuleIds,
            'total_paid_kes' => $totalPaidKes,
            'upgrade_price' => $this->formatPrice($upgradePriceKes),
        ];
    }
}
