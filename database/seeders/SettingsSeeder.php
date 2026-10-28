<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Branding (theme colors applied site-wide by the frontend)
            [
                'key' => 'brand_primary_color',
                'value' => '#c8913a',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'Primary Brand Color',
                'description' => 'Main accent color (buttons, highlights, links). Hex format, e.g. #c8913a.',
                'is_public' => true,
            ],
            [
                'key' => 'brand_secondary_color',
                'value' => '#2f6fe4',
                'type' => 'string',
                'group' => 'branding',
                'label' => 'Secondary Brand Color',
                'description' => 'Supporting color (navigation highlights, info accents). Hex format, e.g. #2f6fe4.',
                'is_public' => true,
            ],

            // Uploads
            [
                'key' => 'max_video_upload_mb',
                'value' => 200,
                'type' => 'integer',
                'group' => 'uploads',
                'label' => 'Maximum Video Upload (MB)',
                'description' => 'Maximum allowed size for student video assessment uploads in megabytes.',
                'is_public' => true,
            ],
            [
                'key' => 'max_audio_upload_mb',
                'value' => 50,
                'type' => 'integer',
                'group' => 'uploads',
                'label' => 'Maximum Audio Upload (MB)',
                'description' => 'Maximum allowed size for audio assignment uploads in megabytes.',
                'is_public' => true,
            ],
            [
                'key' => 'max_pdf_upload_mb',
                'value' => 20,
                'type' => 'integer',
                'group' => 'uploads',
                'label' => 'Maximum PDF Upload (MB)',
                'description' => 'Maximum allowed size for sheet music and PDF lessons.',
                'is_public' => true,
            ],
            [
                'key' => 'accepted_video_formats',
                'value' => ['mp4', 'webm'],
                'type' => 'json',
                'group' => 'uploads',
                'label' => 'Accepted Video Formats',
                'description' => 'Permitted video file formats for submissions.',
                'is_public' => true,
            ],
            [
                'key' => 'year_one_load_students',
                'value' => 500,
                'type' => 'integer',
                'group' => 'system',
                'label' => 'Year One Expected Students',
                'description' => 'Capacity planning target for initial year load.',
                'is_public' => false,
            ],

            // Tutoring
            [
                'key' => 'tutoring_package_sessions',
                'value' => 5,
                'type' => 'integer',
                'group' => 'tutoring',
                'label' => 'Tutoring Package Sessions',
                'description' => 'Default number of 1-on-1 sessions per tutoring bundle.',
                'is_public' => true,
            ],
            [
                'key' => 'tutoring_package_expiry_days',
                'value' => 90,
                'type' => 'integer',
                'group' => 'tutoring',
                'label' => 'Tutoring Package Expiry (Days)',
                'description' => 'Days before unbooked tutoring sessions expire.',
                'is_public' => true,
            ],
            [
                'key' => 'tutoring_slot_length_minutes',
                'value' => 60,
                'type' => 'integer',
                'group' => 'tutoring',
                'label' => 'Session Duration (Minutes)',
                'description' => 'Length of each individual tutoring slot.',
                'is_public' => true,
            ],
            [
                'key' => 'tutoring_buffer_minutes',
                'value' => 15,
                'type' => 'integer',
                'group' => 'tutoring',
                'label' => 'Buffer Between Sessions (Minutes)',
                'description' => 'Mandatory rest/turnaround time between consecutive sessions.',
                'is_public' => false,
            ],
            [
                'key' => 'reschedule_notice_hours',
                'value' => 24,
                'type' => 'integer',
                'group' => 'tutoring',
                'label' => 'Reschedule Notice Period (Hours)',
                'description' => 'Minimum advance notice required to reschedule without forfeit.',
                'is_public' => true,
            ],

            // Assessment & Exams
            [
                'key' => 'exam_pass_mark_percent',
                'value' => 70,
                'type' => 'integer',
                'group' => 'assessment',
                'label' => 'Final Exam Pass Mark (%)',
                'description' => 'Required score percentage to pass course final examination.',
                'is_public' => true,
            ],
            [
                'key' => 'assignment_pass_mark_percent',
                'value' => 70,
                'type' => 'integer',
                'group' => 'assessment',
                'label' => 'Assignment Pass Mark (%)',
                'description' => 'Required rubric score percentage to pass practical module assignments.',
                'is_public' => true,
            ],
            [
                'key' => 'exam_max_attempts',
                'value' => 3,
                'type' => 'integer',
                'group' => 'assessment',
                'label' => 'Maximum Exam Attempts',
                'description' => 'Number of permitted exam attempts per enrollment package.',
                'is_public' => true,
            ],
            [
                'key' => 'exam_attempt_wait_days',
                'value' => 7,
                'type' => 'integer',
                'group' => 'assessment',
                'label' => 'Wait Time Between Attempts (Days)',
                'description' => 'Mandatory cooling off study period before retaking a failed exam.',
                'is_public' => true,
            ],
            [
                'key' => 'exam_retake_package_price_kes',
                'value' => 500000,
                'type' => 'integer',
                'group' => 'assessment',
                'label' => 'Exam Retake Package Price (KES Minor Units)',
                'description' => 'Cost in minor units (5,000 KES) to purchase an additional 3 exam attempts.',
                'is_public' => true,
            ],

            // Refunds
            [
                'key' => 'refund_window_days',
                'value' => 14,
                'type' => 'integer',
                'group' => 'refunds',
                'label' => 'Refund Window (Days)',
                'description' => 'Days from order payment within which refund requests are accepted.',
                'is_public' => true,
            ],
            [
                'key' => 'refund_max_course_progress_percent',
                'value' => 50,
                'type' => 'integer',
                'group' => 'refunds',
                'label' => 'Max Progress for Refund (%)',
                'description' => 'Refund requests are rejected if course completion exceeds this limit.',
                'is_public' => true,
            ],

            // Orders & Bookings
            [
                'key' => 'order_expiry_minutes',
                'value' => 30,
                'type' => 'integer',
                'group' => 'payments',
                'label' => 'Unpaid Order Expiry (Minutes)',
                'description' => 'Duration an unpaid checkout order remains valid before auto-cancellation.',
                'is_public' => false,
            ],
            [
                'key' => 'bootcamp_seat_hold_online_minutes',
                'value' => 15,
                'type' => 'integer',
                'group' => 'bookings',
                'label' => 'Bootcamp Online Seat Hold (Minutes)',
                'description' => 'Time a physical workshop seat is locked during active checkout.',
                'is_public' => true,
            ],
            [
                'key' => 'bootcamp_seat_hold_cash_hours',
                'value' => 48,
                'type' => 'integer',
                'group' => 'bookings',
                'label' => 'Bootcamp Cash Seat Hold (Hours)',
                'description' => 'Time finance staff can hold an in-person event seat pending school cash receipt.',
                'is_public' => false,
            ],
            [
                'key' => 'bootcamp_refund_window_days',
                'value' => 7,
                'type' => 'integer',
                'group' => 'bookings',
                'label' => 'Bootcamp Refund Request Window (Days)',
                'description' => 'Minimum days prior to event start date when a refund request can be submitted.',
                'is_public' => true,
            ],

            // Currency & Tax
            [
                'key' => 'usd_exchange_rate',
                'value' => 130.00,
                'type' => 'float',
                'group' => 'currency',
                'label' => 'USD Exchange Rate (KES per 1 USD)',
                'description' => 'Fixed rate used to convert KES minor units to display USD prices.',
                'is_public' => true,
            ],
            [
                'key' => 'vat_rate_percent',
                'value' => 0.00,
                'type' => 'float',
                'group' => 'tax',
                'label' => 'VAT Rate (%)',
                'description' => 'Applicable VAT rate included in catalog prices.',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(['key' => $item['key']], $item);
        }
    }
}
