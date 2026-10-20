<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CoursesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first();
        $instructor = User::role('instructor')->first();
        $student = User::where('email', 'student.adult@bmas.com')->first();

        // Create sample media files in private storage disk for testing
        $privateDisk = config('filesystems.private_disk', 'local');
        $samplePdfPath = 'lessons/piano_clefs_guide.pdf';
        $sampleAudioPath = 'lessons/ear_training_lesson_1.mp3';

        if (! Storage::disk($privateDisk)->exists($samplePdfPath)) {
            Storage::disk($privateDisk)->put(
                $samplePdfPath,
                "%PDF-1.4\n1 0 obj\n<< /Title (Bariton Piano Clefs Guide) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
            );
        }

        if (! Storage::disk($privateDisk)->exists($sampleAudioPath)) {
            // A minimal mock audio payload with ID3 header bytes
            Storage::disk($privateDisk)->put(
                $sampleAudioPath,
                "ID3\x03\x00\x00\x00\x00\x00\x10Bariton Audio Exercise 1 Sample Data..."
            );
        }

        // 1. Create Flagship Course
        $course = Course::updateOrCreate(
            ['slug' => 'classical-piano-mastery'],
            [
                'title' => 'Classical Piano Mastery',
                'description' => 'A comprehensive conservatory-level piano program designed by Bariton Music Academy master instructors. Structured into three progressive levels (Beginner, Intermediate, and Advanced) with video masterclasses, downloadable PDF scores, and guided audio listening.',
                'short_description' => 'Master classical piano from fundamental keyboard geography and reading sheet music to performing virtuoso concert repertoire.',
                'status' => 'published',
                'bundle_discount_type' => 'percent',
                'bundle_discount_value' => 15,
                'requires_sequential_levels' => true,
                'created_by' => $admin?->id,
            ]
        );

        if ($instructor) {
            $course->instructors()->syncWithoutDetaching([
                $instructor->id => ['is_primary' => true],
            ]);
        }

        // 2. Level 1 Module (Beginner)
        $module1 = Module::updateOrCreate(
            [
                'course_id' => $course->id,
                'slug' => 'beginner-piano-foundations',
            ],
            [
                'title' => 'Beginner: Piano Foundations & Note Reading',
                'description' => 'Posture, hand position, rhythm, and reading standard musical notation on the grand staff.',
                'level' => 'beginner',
                'order' => 1,
                'price_kes' => 450000, // KES 4,500.00
            ]
        );

        Lesson::updateOrCreate(
            ['module_id' => $module1->id, 'slug' => 'posture-hand-position'],
            [
                'title' => 'Posture, Hand Position & Keyboard Geography',
                'summary' => 'Introduction to piano posture, seated position, curved fingers, and finding all 88 keys.',
                'order' => 1,
                'type' => 'youtube',
                'youtube_video_id' => 'Mh4f9AYRCZY',
                'duration_seconds' => 720,
                'is_preview' => true,
            ]
        );

        Lesson::updateOrCreate(
            ['module_id' => $module1->id, 'slug' => 'treble-and-bass-clefs'],
            [
                'title' => 'Treble and Bass Clef Decoded',
                'summary' => 'Downloadable reference guide with mnemonic exercises for reading notes on the grand staff.',
                'order' => 2,
                'type' => 'pdf',
                'file_path' => $samplePdfPath,
                'file_disk' => $privateDisk,
                'file_name' => 'piano_clefs_guide.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 1024,
                'duration_seconds' => 600,
                'is_preview' => false,
            ]
        );

        Lesson::updateOrCreate(
            ['module_id' => $module1->id, 'slug' => 'five-finger-ear-training'],
            [
                'title' => 'Five-Finger Ear Training Exercises',
                'summary' => 'Guided ear training listening audio to identify major vs minor intervals and pitch intervals.',
                'order' => 3,
                'type' => 'audio',
                'file_path' => $sampleAudioPath,
                'file_disk' => $privateDisk,
                'file_name' => 'ear_training_lesson_1.mp3',
                'mime_type' => 'audio/mpeg',
                'file_size_bytes' => 2048,
                'duration_seconds' => 300,
                'is_preview' => false,
            ]
        );

        // 3. Level 2 Module (Intermediate)
        $module2 = Module::updateOrCreate(
            [
                'course_id' => $course->id,
                'slug' => 'intermediate-scales-and-chords',
            ],
            [
                'title' => 'Intermediate: Scales, Chords & Dynamic Articulation',
                'description' => 'Two-octave scales, triads, arpeggios, damper pedal technique, and phrasing.',
                'level' => 'intermediate',
                'order' => 2,
                'price_kes' => 650000, // KES 6,500.00
            ]
        );

        Lesson::updateOrCreate(
            ['module_id' => $module2->id, 'slug' => 'major-scales-and-arpeggios'],
            [
                'title' => 'Major Scales and Finger Crossings',
                'summary' => 'Thumb-under fluid mechanics for C, G, D, and F major scales.',
                'order' => 1,
                'type' => 'youtube',
                'youtube_video_id' => 'yq2dJj6B4m4',
                'duration_seconds' => 840,
                'is_preview' => false,
            ]
        );

        // 4. Level 3 Module (Advanced)
        $module3 = Module::updateOrCreate(
            [
                'course_id' => $course->id,
                'slug' => 'advanced-polyphony-and-repertoire',
            ],
            [
                'title' => 'Advanced: Baroque Polyphony & Romantic Repertoire',
                'description' => 'Complex contrapuntal voice leading, rubato, and concert performance interpretation.',
                'level' => 'advanced',
                'order' => 3,
                'price_kes' => 900000, // KES 9,000.00
            ]
        );

        Lesson::updateOrCreate(
            ['module_id' => $module3->id, 'slug' => 'bach-two-part-inventions'],
            [
                'title' => 'Bach Two-Part Invention No. 1 in C Major',
                'summary' => 'In-depth analysis of subject, countersubject, and voicing between right and left hands.',
                'order' => 1,
                'type' => 'youtube',
                'youtube_video_id' => 'u7K_A06ZPXs',
                'duration_seconds' => 950,
                'is_preview' => false,
            ]
        );

        // 5. Seed an active enrollment for the adult student with Module 1 unlocked
        if ($student) {
            $enrollment = Enrollment::firstOrCreate(
                [
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                ],
                [
                    'path_type' => 'standard',
                    'status' => 'active',
                    'progress_percent' => 33.33,
                ]
            );

            EnrollmentModule::firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'module_id' => $module1->id,
                ],
                [
                    'amount_paid_kes' => 450000, // Paid KES 4,500.00 for module 1
                    'unlocked_at' => now(),
                ]
            );

            // Record progress on lesson 1 (completed)
            $firstLesson = $module1->lessons()->first();
            if ($firstLesson) {
                LessonProgress::firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'lesson_id' => $firstLesson->id,
                    ],
                    [
                        'status' => 'completed',
                        'progress_percent' => 100,
                        'last_position_seconds' => 720,
                        'completed_at' => now(),
                        'last_watched_at' => now(),
                    ]
                );
            }
        }
    }
}
