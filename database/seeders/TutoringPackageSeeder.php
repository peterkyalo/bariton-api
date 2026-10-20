<?php

namespace Database\Seeders;

use App\Models\TutoringPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TutoringPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'title' => 'Single Private Master Coaching Session (1 Hour)',
                'slug' => 'single-private-master-session',
                'description' => 'One-on-one intensive private lesson with a master instructor. Focus on technique, repertoire, or audition preparation.',
                'session_count' => 1,
                'price_kes' => 500000, // 5,000 KES
                'price_usd_cents' => 3850, // $38.50
                'validity_days' => 30,
                'is_active' => true,
            ],
            [
                'title' => 'Standard Virtuoso Coaching Bundle (5 Sessions)',
                'slug' => 'standard-virtuoso-coaching-bundle',
                'description' => 'Five 60-minute private mentorship sessions. Ideal for dedicated progress through module material or recital milestones.',
                'session_count' => 5,
                'price_kes' => 2250000, // 22,500 KES (10% bundle discount)
                'price_usd_cents' => 17300,
                'validity_days' => 90,
                'is_active' => true,
            ],
            [
                'title' => 'Comprehensive Artist Mentorship Pack (10 Sessions)',
                'slug' => 'comprehensive-artist-mentorship-pack',
                'description' => 'Ten 60-minute private lessons with comprehensive artistic development, ear-training diagnostics, and career advice.',
                'session_count' => 10,
                'price_kes' => 4000000, // 40,000 KES (20% bundle discount)
                'price_usd_cents' => 30770,
                'validity_days' => 180,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $pkg) {
            TutoringPackage::firstOrCreate(
                ['slug' => $pkg['slug']],
                array_merge($pkg, ['uuid' => (string) Str::uuid()])
            );
        }
    }
}
