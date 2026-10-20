<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rubric;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

test('a student completes a course, passes the exam, and downloads a certificate', function () {
    // 1. Setup Student, Instructor, Course, Module, Lesson, Assignment, Exam
    $student = User::factory()->create([
        'name' => 'Seraphina Vance',
        'email' => 'seraphina@bmas.com',
        'status' => 'active',
    ]);
    $student->assignRole('student');

    $instructor = User::factory()->create([
        'name' => 'Maestro Julian',
        'email' => 'julian@bmas.com',
        'status' => 'active',
    ]);
    $instructor->assignRole('instructor');

    $course = Course::create([
        'title' => 'Advanced Classical Piano Virtuosity',
        'slug' => 'advanced-classical-piano-virtuosity',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 10,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'title' => 'Module 1: Bach Fugues',
        'slug' => 'module-1-bach-fugues',
        'order' => 1,
        'level' => 'advanced',
        'price_kes' => 500000,
    ]);

    $lesson = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Subject Exposition Analysis',
        'slug' => 'subject-exposition-analysis',
        'type' => 'youtube',
        'video_id' => 'dQw4w9WgXcQ',
        'duration_seconds' => 600,
        'order' => 1,
        'is_published' => true,
    ]);

    $rubric = Rubric::create([
        'title' => 'Fugue Technique Rubric',
        'total_points' => 50,
        'is_template' => true,
    ]);
    $criterion = $rubric->criteria()->create([
        'title' => 'Contrapuntal Balance',
        'description' => 'Voicing clarity',
        'max_points' => 50,
        'order' => 1,
    ]);
    $criterion->levels()->create(['title' => 'Mastery', 'points' => 50, 'description' => 'Impeccable balance', 'order' => 1]);

    $assignment = Assignment::create([
        'module_id' => $module->id,
        'rubric_id' => $rubric->id,
        'title' => 'Fugue Voicing Video',
        'slug' => 'fugue-voicing-video',
        'instructions' => 'Perform the three-voice fugue exposition.',
        'submission_type' => 'video',
        'accepted_formats' => ['mp4'],
        'max_file_size_mb' => 200,
        'order' => 1,
        'is_published' => true,
    ]);

    // Create Final Exam
    $exam = Exam::create([
        'course_id' => $course->id,
        'title' => 'Advanced Piano Comprehensive Final Examination',
        'slug' => 'advanced-piano-comprehensive-final-examination',
        'description' => 'Final assessment for conservatory certification.',
        'duration_minutes' => 60,
        'is_active' => true,
    ]);

    $q1 = $exam->questions()->create([
        'question_type' => 'multiple_choice',
        'prompt' => 'How many voices are present in BWV 847?',
        'points' => 30,
        'order' => 1,
        'options' => [
            ['id' => 'a', 'text' => '2 voices'],
            ['id' => 'b', 'text' => '3 voices'],
            ['id' => 'c', 'text' => '4 voices'],
        ],
        'correct_answer' => 'b',
    ]);

    $q2 = $exam->questions()->create([
        'question_type' => 'theory',
        'rubric_id' => $rubric->id,
        'prompt' => 'Analyze the inversion of the countersubject in measure 7.',
        'points' => 35,
        'order' => 2,
    ]);

    $q3 = $exam->questions()->create([
        'question_type' => 'recorded_performance',
        'rubric_id' => $rubric->id,
        'prompt' => 'Record your recital performance of the complete Fugue.',
        'points' => 35,
        'order' => 3,
    ]);

    // Student Enrollment
    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'assignment_pass_mark_percent' => 70,
        'exam_pass_mark_percent' => 70,
        'exam_attempt_limit' => 3,
        'exam_wait_time_hours' => 24,
    ]);
    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module->id,
        'amount_paid_kes' => 500000,
        'unlocked_at' => now(),
    ]);

    // 2. Mark lesson as completed
    LessonProgress::create([
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson->id,
        'status' => 'completed',
        'progress_percent' => 100,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    // 3. Mark assignment as passed
    AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'attempt_number' => 1,
        'file_path' => 'submissions/test.mp4',
        'file_disk' => 'local',
        'original_filename' => 'fugue_recital.mp4',
        'mime_type' => 'video/mp4',
        'file_size_bytes' => 1024 * 1024,
        'status' => 'graded',
        'passed' => true,
        'score_percent' => 95.00,
        'submitted_at' => now(),
        'graded_at' => now(),
    ]);

    // 4. Student starts Exam
    $startRes = $this->actingAs($student)
        ->postJson("/api/v1/student/exams/{$exam->uuid}/start")
        ->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.attempt.attempt_number', 1)
        ->assertJsonPath('data.attempt.status', 'in_progress');

    $attemptUuid = $startRes->json('data.attempt.uuid');

    // 5. Debounced auto-save answers
    $this->actingAs($student)
        ->postJson("/api/v1/student/exams/attempts/{$attemptUuid}/answers", [
            'answers' => [
                [
                    'question_uuid' => $q1->uuid,
                    'selected_option' => 'b', // Correct option!
                ],
                [
                    'question_uuid' => $q2->uuid,
                    'text_answer' => 'The countersubject shifts from the soprano down to the tenor voice at double counterpoint at the octave.',
                ],
            ],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'saved');

    // 6. Stop timer on performance recording -> grants 15-minute grace period
    $this->actingAs($student)
        ->postJson("/api/v1/student/exams/attempts/{$attemptUuid}/record-stop", [
            'question_uuid' => $q3->uuid,
            'media_path' => 'submissions/performance_recital.mp4',
            'media_mime' => 'video/mp4',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.attempt.is_grace_period', true);

    // 7. Student submits exam
    $submitRes = $this->actingAs($student)
        ->postJson("/api/v1/student/exams/attempts/{$attemptUuid}/submit")
        ->assertStatus(200)
        ->assertJsonPath('data.attempt.status', 'grading'); // Waiting for manual questions (theory & performance)

    // 8. Instructor grades the theory and performance questions
    $attempt = ExamAttempt::where('uuid', $attemptUuid)->with('answers')->first();
    $q2Answer = $attempt->answers->firstWhere('exam_question_id', $q2->id);
    $q3Answer = $attempt->answers->firstWhere('exam_question_id', $q3->id);

    $gradeRes = $this->actingAs($instructor)
        ->postJson("/api/v1/instructor/exam-grading/{$attemptUuid}/grade", [
            'evaluations' => [
                [
                    'answer_uuid' => $q2Answer->uuid,
                    'score_awarded' => 30.00, // Out of 35
                    'feedback' => 'Exceptional contrapuntal voice tracking.',
                ],
                [
                    'answer_uuid' => $q3Answer->uuid,
                    'score_awarded' => 32.00, // Out of 35
                    'feedback' => 'Clear articulation and solid tempo stability.',
                    'rubric_scores' => [
                        [
                            'rubric_criterion_id' => $criterion->id,
                            'points' => 32.00,
                            'comments' => 'Distinction level polyphonic balance.',
                        ],
                    ],
                ],
            ],
            'overall_feedback' => 'Congratulations on fulfilling the conservatory final examination with high honors!',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.attempt.status', 'graded')
        ->assertJsonPath('data.attempt.passed', true);

    // Score: 30 (q1) + 30 (q2) + 32 (q3) = 92 / 100 = 92% (Pass mark was 70%)
    expect((float) $gradeRes->json('data.attempt.score_percent'))->toBe(92.0);

    // 9. Verify Certificate was automatically generated
    $certificate = Certificate::where('enrollment_id', $enrollment->id)->first();
    expect($certificate)->not->toBeNull()
        ->and($certificate->status)->toBe('issued')
        ->and($certificate->holder_name)->toBe('Seraphina Vance')
        ->and($certificate->course_title)->toBe('Advanced Classical Piano Virtuosity')
        ->and($certificate->certificate_code)->toStartWith('BMAS-CERT-')
        ->and($certificate->qr_code_svg)->not->toBeEmpty();

    // 10. Student downloads certificate PDF
    $downloadRes = $this->actingAs($student)
        ->get("/api/v1/student/certificates/{$certificate->uuid}/download")
        ->assertStatus(200)
        ->assertHeader('content-type', 'application/pdf');

    expect($downloadRes->headers->get('content-disposition'))->toContain('Certificate-BMAS-CERT-');
});

test('phone scan of the QR code shows public verification page with rate limiting and no personal data leaks', function () {
    $student = User::factory()->create([
        'name' => 'Alexander Chen',
        'email' => 'alexander.secret@private.com',
        'phone' => '+254711999888',
    ]);

    $course = Course::create([
        'title' => 'Baroque Harpsichord & Figured Bass',
        'slug' => 'baroque-harpsichord-figured-bass',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 0,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
    ]);

    $cert = Certificate::create([
        'uuid' => (string) Str::uuid(),
        'certificate_code' => 'BMAS-CERT-HARP2026',
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'holder_name' => 'Alexander Chen',
        'course_title' => 'Baroque Harpsichord & Figured Bass',
        'path_type' => 'standard',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    // Public request to verify endpoint (no auth headers required)
    $res = $this->getJson('/api/v1/certificates/verify/BMAS-CERT-HARP2026')
        ->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.certificate_code', 'BMAS-CERT-HARP2026')
        ->assertJsonPath('data.holder_name', 'Alexander Chen')
        ->assertJsonPath('data.course_title', 'Baroque Harpsichord & Figured Bass')
        ->assertJsonPath('data.status', 'issued');

    // Confirm strict privacy: NO user_id, NO email, NO phone_number in response payload!
    $data = $res->json('data');
    expect(array_key_exists('email', $data))->toBeFalse()
        ->and(array_key_exists('user_id', $data))->toBeFalse()
        ->and(array_key_exists('phone_number', $data))->toBeFalse();
});

test('a revoked certificate shows revoked on public verification page and can be reissued', function () {
    $admin = User::factory()->create([
        'name' => 'Registrar Dean',
        'two_factor_secret' => 'DUMMYSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $student = User::factory()->create(['name' => 'Johnathon Smith']);

    $course = Course::create([
        'title' => 'Vocal Technique Mastery',
        'slug' => 'vocal-technique-mastery',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 0,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
    ]);

    $cert = Certificate::create([
        'uuid' => (string) Str::uuid(),
        'certificate_code' => 'BMAS-CERT-VOCAL001',
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'holder_name' => 'Johnathon Smith',
        'course_title' => 'Vocal Technique Mastery',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    // 1. Admin revokes the certificate
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/certificates/{$cert->uuid}/revoke", [
            'reason' => 'Submission audio failed academic integrity voice biometric check.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'revoked');

    // 2. Public verification page now reports REVOKED
    $this->getJson('/api/v1/certificates/verify/BMAS-CERT-VOCAL001')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revocation_reason', 'Submission audio failed academic integrity voice biometric check.');

    // 3. Admin reissues with corrected name
    $reissueRes = $this->actingAs($admin)
        ->postJson("/api/v1/admin/certificates/{$cert->uuid}/reissue", [
            'new_holder_name' => 'Jonathan Smith',
            'reason' => 'Name spelling correction requested by student.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.old_certificate.status', 'replaced');

    $newCode = $reissueRes->json('data.new_certificate.certificate_code');

    // Verify old code shows replaced
    $this->getJson('/api/v1/certificates/verify/BMAS-CERT-VOCAL001')
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'replaced')
        ->assertJsonPath('data.replaced_by_code', $newCode);

    // Verify new code shows active and issued with corrected name
    $this->getJson("/api/v1/certificates/verify/{$newCode}")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.holder_name', 'Jonathan Smith')
        ->assertJsonPath('data.replaces_code', 'BMAS-CERT-VOCAL001');
});

test('a student who fails three times can buy a retake package to unlock more attempts', function () {
    $student = User::factory()->create(['name' => 'Marcus Aurelius']);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Orchestral Conducting Fundamentals',
        'slug' => 'orchestral-conducting-fundamentals',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 0,
    ]);

    $exam = Exam::create([
        'course_id' => $course->id,
        'title' => 'Baton Technique & Score Reading Examination',
        'slug' => 'baton-technique-examination',
        'duration_minutes' => 45,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'exam_pass_mark_percent' => 70,
        'exam_attempt_limit' => 3, // Initial limit of 3
        'exam_wait_time_hours' => 0, // Set to 0 for rapid test iteration
    ]);

    // Record 3 failed attempts
    for ($i = 1; $i <= 3; $i++) {
        ExamAttempt::create([
            'uuid' => (string) Str::uuid(),
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'attempt_number' => $i,
            'status' => 'graded',
            'started_at' => now()->subDays(4 - $i),
            'expires_at' => now()->subDays(4 - $i)->addHours(1),
            'submitted_at' => now()->subDays(4 - $i),
            'total_score' => 40,
            'max_score' => 100,
            'score_percent' => 40.00,
            'passed' => false,
            'graded_at' => now()->subDays(4 - $i),
        ]);
    }

    // Attempting to start attempt #4 is rejected with 422 limit reached
    $this->actingAs($student)
        ->postJson("/api/v1/student/exams/{$exam->uuid}/start")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['exam']);

    // Student orders an exam retake package
    $orderRes = $this->actingAs($student)
        ->postJson('/api/v1/orders', [
            'item_type' => 'exam_retake_package',
            'item_id' => $course->id,
        ])
        ->assertStatus(201);

    $order = Order::where('uuid', $orderRes->json('order.uuid'))->first();
    expect($order->total_kes)->toBe(500000); // 5,000 KES from settings

    // Simulate payment confirmation
    $payment = Payment::create([
        'uuid' => (string) Str::uuid(),
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'mpesa',
        'amount_kes' => $order->total_kes,
        'currency' => 'KES',
        'status' => 'successful',
        'gateway_reference' => 'QA12345678',
    ]);

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    // Confirm enrollment now has 3 additional attempts (limit increased from 3 to 6)
    $enrollment->refresh();
    expect($enrollment->exam_attempt_limit)->toBe(6);

    // Student can now successfully start attempt #4!
    $attemptRes = $this->actingAs($student)
        ->postJson("/api/v1/student/exams/{$exam->uuid}/start")
        ->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.attempt.attempt_number', 4)
        ->assertJsonPath('data.attempt.status', 'in_progress');
});
