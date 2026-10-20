<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => 'Nairobi International Jazz Masterclass & Jam Intensive',
                'slug' => 'nairobi-international-jazz-masterclass',
                'type' => 'masterclass',
                'description' => 'A 4-day deep dive into modern jazz harmony, modal interchange, syncopated polyrhythms, and live ensemble playing led by renowned African jazz virtuosos.',
                'venue' => 'BMAS Main Auditorium, Argwings Kodhek Road, Kilimani, Nairobi',
                'start_date' => now()->addDays(20)->setTime(9, 0, 0),
                'end_date' => now()->addDays(24)->setTime(17, 0, 0),
                'capacity' => 25,
                'training_fee_kes' => 3500000, // 35,000 KES
                'has_accommodation' => true,
                'accommodation_fee_kes' => 1500000, // 15,000 KES
                'status' => 'published',
            ],
            [
                'title' => 'African Gospel Keyboard & Choral Arranging Workshop',
                'slug' => 'african-gospel-keyboard-workshop',
                'type' => 'workshop',
                'description' => 'Learn authentic African praise progressions, passing chords, tritone substitutions, and vocal choir parts direct from veteran music directors.',
                'venue' => 'BMAS Studio Hall B, Kilimani, Nairobi',
                'start_date' => now()->addDays(35)->setTime(9, 30, 0),
                'end_date' => now()->addDays(37)->setTime(16, 30, 0),
                'capacity' => 30,
                'training_fee_kes' => 2000000, // 20,000 KES
                'has_accommodation' => false,
                'accommodation_fee_kes' => 0,
                'status' => 'published',
            ],
            [
                'title' => 'Contemporary Vocal Production & Mic Technique Intensive',
                'slug' => 'vocal-production-mic-technique',
                'type' => 'bootcamp',
                'description' => 'Master diaphragmatic control, blend register transitions, and live studio recording microphone techniques with industry recording engineers.',
                'venue' => 'BMAS Live Recording Studio 1, Kilimani, Nairobi',
                'start_date' => now()->addDays(45)->setTime(10, 0, 0),
                'end_date' => now()->addDays(47)->setTime(18, 0, 0),
                'capacity' => 15,
                'training_fee_kes' => 2800000, // 28,000 KES
                'has_accommodation' => true,
                'accommodation_fee_kes' => 1200000, // 12,000 KES
                'status' => 'published',
            ],
        ];

        foreach ($events as $item) {
            Event::firstOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, ['uuid' => (string) Str::uuid()])
            );
        }
    }
}
