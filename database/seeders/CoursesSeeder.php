<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Rubric;
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
                'is_open_for_validation' => true,
                'validation_price_kes' => 1200000,
                'validation_price_usd' => 9500,
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

        // 6. Seed Reusable Rubric Template
        $rubric = Rubric::updateOrCreate(
            ['title' => 'Piano Conservatory Repertoire Assessment Rubric'],
            [
                'instructor_id' => $instructor?->id,
                'description' => 'Comprehensive evaluation rubric for classical piano technique, rhythm, and artistic interpretation.',
                'total_points' => 100,
                'is_template' => true,
            ]
        );

        if ($rubric->criteria()->count() === 0) {
            $crit1 = $rubric->criteria()->create([
                'title' => 'Rhythm & Tempo Stability',
                'description' => 'Steady pulse, accurate subdivision, and metronomic precision.',
                'max_points' => 25,
                'order' => 1,
            ]);
            $crit1->levels()->create(['title' => 'Distinction (Flawless)', 'points' => 25, 'description' => 'Impeccable pulse with natural rubato control.', 'order' => 1]);
            $crit1->levels()->create(['title' => 'Merit (Consistent)', 'points' => 20, 'description' => 'Minor hesitation without rhythm breakdown.', 'order' => 2]);
            $crit1->levels()->create(['title' => 'Pass (Acceptable)', 'points' => 15, 'description' => 'Noticeable rushing or dragging in transitions.', 'order' => 3]);
            $crit1->levels()->create(['title' => 'Developing', 'points' => 5, 'description' => 'Erratic tempo and missed subdivisions.', 'order' => 4]);

            $crit2 = $rubric->criteria()->create([
                'title' => 'Note Accuracy & Fingering',
                'description' => 'Correct pitch execution and standard conservatory fingerings.',
                'max_points' => 25,
                'order' => 2,
            ]);
            $crit2->levels()->create(['title' => 'Distinction (100% Accurate)', 'points' => 25, 'description' => 'Zero wrong notes; fluid fingering switches.', 'order' => 1]);
            $crit2->levels()->create(['title' => 'Merit (Minor Slips)', 'points' => 20, 'description' => '1-2 isolated false notes recovered quickly.', 'order' => 2]);
            $crit2->levels()->create(['title' => 'Pass (Moderate Errors)', 'points' => 15, 'description' => 'Several pitch errors but progression maintained.', 'order' => 3]);
            $crit2->levels()->create(['title' => 'Developing', 'points' => 5, 'description' => 'Frequent stops and incorrect fingerings.', 'order' => 4]);

            $crit3 = $rubric->criteria()->create([
                'title' => 'Tone, Dynamics & Articulation',
                'description' => 'Balance between hands, cantabile melody, and dynamics (p to ff).',
                'max_points' => 25,
                'order' => 3,
            ]);
            $crit3->levels()->create(['title' => 'Distinction (Artistic)', 'points' => 25, 'description' => 'Deep expressive tone with crystal clear voicing.', 'order' => 1]);
            $crit3->levels()->create(['title' => 'Merit (Expressive)', 'points' => 20, 'description' => 'Good dynamic contrast with clear melody.', 'order' => 2]);
            $crit3->levels()->create(['title' => 'Pass (Flat Dynamics)', 'points' => 15, 'description' => 'Audible notes but lacking dynamic hierarchy.', 'order' => 3]);
            $crit3->levels()->create(['title' => 'Developing', 'points' => 5, 'description' => 'Harsh tone or unbalanced left-hand dominance.', 'order' => 4]);

            $crit4 = $rubric->criteria()->create([
                'title' => 'Posture, Hand Arch & Ergonomics',
                'description' => 'Curved fingers, relaxed wrists, erect spine, and weight transfer.',
                'max_points' => 25,
                'order' => 4,
            ]);
            $crit4->levels()->create(['title' => 'Distinction (Exemplary)', 'points' => 25, 'description' => 'Perfect relaxed wrist drape and finger arches.', 'order' => 1]);
            $crit4->levels()->create(['title' => 'Merit (Good Form)', 'points' => 20, 'description' => 'Relaxed posture with slight wrist tension.', 'order' => 2]);
            $crit4->levels()->create(['title' => 'Pass (Tension Present)', 'points' => 15, 'description' => 'Flat fingers or elevated shoulders observed.', 'order' => 3]);
            $crit4->levels()->create(['title' => 'Developing', 'points' => 5, 'description' => 'Collapsed arches and excessive physical tension.', 'order' => 4]);
        }

        // 7. Seed Assignments for Module 1 & Module 2
        Assignment::updateOrCreate(
            ['module_id' => $module1->id, 'slug' => 'level-1-piano-posture-recording'],
            [
                'rubric_id' => $rubric->id,
                'title' => 'Level 1: Five-Finger Patterns & Posture Video Assessment',
                'instructions' => "Record a 2 to 3-minute video showing your complete posture, hand position at the keyboard, and performance of the C Major and G Major five-finger pattern exercises. Ensure the camera angle shows both your hands and upper body profile clearly.\n\n### Requirements:\n1. Unedited continuous performance.\n2. State your name and exercise title clearly before playing.\n3. Observe hand arch and relaxed wrist releases at the end of each pattern.",
                'submission_type' => 'video',
                'accepted_formats' => ['mp4', 'webm'],
                'max_file_size_mb' => 200,
                'order' => 1,
                'is_published' => true,
            ]
        );

        Assignment::updateOrCreate(
            ['module_id' => $module2->id, 'slug' => 'level-2-major-scales-assessment'],
            [
                'rubric_id' => $rubric->id,
                'title' => 'Level 2: Major Scales & Finger Crossings Performance Video',
                'instructions' => 'Perform C Major, G Major, and D Major scales hands together, two octaves ascending and descending, at a minimum tempo of 60 BPM with metronome audible. Focus on thumb-under fluidity without wrist jerking.',
                'submission_type' => 'video',
                'accepted_formats' => ['mp4', 'webm'],
                'max_file_size_mb' => 200,
                'order' => 1,
                'is_published' => true,
            ]
        );

        // 8. Seed Course Final Exam (Phase 4b)
        $exam = Exam::updateOrCreate(
            ['course_id' => $course->id, 'slug' => 'classical-piano-mastery-final-examination'],
            [
                'title' => 'Classical Piano Mastery Final Certification Examination',
                'description' => 'Comprehensive theoretical, analytical, and practical conservatory examination assessing keyboard technique, voice leading, and performance execution.',
                'instructions' => "This examination consists of auto-graded multiple choice questions, written theory analysis, and an unedited recorded performance. The server timer will automatically submit your attempt once duration expires. When recording performance, pausing the timer grants a 15-minute upload grace period.\n\nPass mark: 70%. Good luck!",
                'duration_minutes' => 60,
                'is_active' => true,
            ]
        );

        if ($exam->questions()->count() === 0) {
            $exam->questions()->create([
                'question_type' => 'multiple_choice',
                'prompt' => 'Which key signature has three sharps (F#, C#, G#)?',
                'points' => 20,
                'order' => 1,
                'options' => [
                    ['id' => 'a', 'text' => 'G Major'],
                    ['id' => 'b', 'text' => 'D Major'],
                    ['id' => 'c', 'text' => 'A Major'],
                    ['id' => 'd', 'text' => 'E Major'],
                ],
                'correct_answer' => 'c',
                'explanation' => 'A Major has three sharps: F#, C#, and G#.',
            ]);

            $exam->questions()->create([
                'question_type' => 'multiple_choice',
                'prompt' => 'In standard 4/4 common time, how many sixteenth notes (semiquavers) constitute the duration of a dotted half note (dotted minim)?',
                'points' => 20,
                'order' => 2,
                'options' => [
                    ['id' => 'a', 'text' => '8 sixteenth notes'],
                    ['id' => 'b', 'text' => '10 sixteenth notes'],
                    ['id' => 'c', 'text' => '12 sixteenth notes'],
                    ['id' => 'd', 'text' => '16 sixteenth notes'],
                ],
                'correct_answer' => 'c',
                'explanation' => 'A dotted half note equals 3 quarter note beats. Each quarter note contains 4 sixteenth notes (3 x 4 = 12 sixteenth notes).',
            ]);

            $exam->questions()->create([
                'question_type' => 'theory',
                'rubric_id' => $rubric->id,
                'prompt' => 'Describe the physical and anatomical mechanisms required for relaxed, non-fatiguing octave leaps. Detail the coordination between the shoulder girdle, arm weight release, and the bridge support of the hand arch.',
                'points' => 30,
                'order' => 3,
                'explanation' => 'Answers should address gravity-assisted arm drop, flexible shock-absorbing wrists, and an anchored yet unstiffened hand arch.',
            ]);

            $exam->questions()->create([
                'question_type' => 'recorded_performance',
                'rubric_id' => $rubric->id,
                'prompt' => 'Record and upload an unedited continuous performance of J.S. Bach\'s Two-Part Invention No. 1 in C Major (BWV 772). Display clear articulation, strict contrapuntal balance between soprano and bass voices, and consistent tempo.',
                'points' => 30,
                'order' => 4,
                'explanation' => 'Evaluated against the Conservatory Assessment Rubric for tone, rhythm, and interpretative clarity.',
            ]);
        }
    }
}
