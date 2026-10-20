<?php

namespace App\Services;

use App\Models\StudentTutoringPackage;

class TutoringExpiryService
{
    /**
     * Scan and expire student tutoring packages whose validity period has elapsed.
     */
    public function expireUnusedPackages(): int
    {
        $expiredPackages = StudentTutoringPackage::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredPackages as $package) {
            $package->update([
                'status' => 'expired',
            ]);

            activity()
                ->performedOn($package)
                ->withProperties([
                    'unused_credits_forfeited' => $package->remaining_credits,
                    'expired_at' => now()->toISOString(),
                ])
                ->log('Tutoring package expired; unused credits forfeited');

            $count++;
        }

        return $count;
    }
}
