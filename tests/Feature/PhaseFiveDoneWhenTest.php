<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\OrderFulfillmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

test('course marked open for validation exposes validation pricing and allows purchasing assessment package', function () {
    $student = User::factory()->create(['status' => 'active']);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Concert Pianist Diploma',
        'slug' => 'concert-pianist-diploma',
        'status' => 'published',
        'bundle_discount_type' => 'none',
        'bundle_discount_value' => 0,
        'is_open_for_validation' => true,
        'validation_price_kes' => 1200000, // KES 12,000
        'validation_price_usd' => 9500, // $95.00
    ]);

    $module1 = Module::create([
        'course_id' => $course->id,
        'title' => 'Module 1: Technique',
        'slug' => 'mod-1-technique',
        'level' => 1,
        'price_kes' => 1000000,
        'order' => 1,
    ]);

    $module2 = Module::create([
        'course_id' => $course->id,
        'title' => 'Module 2: Repertoire',
        'slug' => 'mod-2-repertoire',
        'level' => 2,
        'price_kes' => 1500000,
        'order' => 2,
    ]);

    Sanctum::actingAs($student);

    // 1. Fetch public course details
    $response = $this->getJson("/api/v1/courses/{$course->slug}");
    $response->assertOk()
        ->assertJsonPath('course.is_open_for_validation', true)
        ->assertJsonPath('course.validation_pricing.price_kes', 1200000)
        ->assertJsonPath('course.validation_pricing.price_kes_formatted', '12,000.00');

    // 2. Buy assessment package
    $orderRes = $this->postJson('/api/v1/orders', [
        'item_type' => 'assessment_package',
        'item_id' => $course->id,
    ]);

    $orderRes->assertStatus(201)
        ->assertJsonPath('order.pricing.total_kes', 1200000)
        ->assertJsonPath('order.items.0.item_type', 'assessment_package');

    $orderUuid = $orderRes->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // 3. Fulfill the order (simulate completed payment)
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'mpesa',
        'reference' => 'PAY-VAL-'.uniqid(),
        'amount_kes' => 1200000,
        'status' => 'pending',
    ]);

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    // 4. Enrollment created with path_type = validation
    $enrollment = Enrollment::where('user_id', $student->id)
        ->where('course_id', $course->id)
        ->firstOrFail();

    expect($enrollment->path_type)->toBe('validation');
    expect($enrollment->status)->toBe('active');
    expect($enrollment->enrollmentModules)->toHaveCount(0); // Lessons not unlocked
});

test('professional sees assessments only and lessons are hidden in curriculum and blocked by policy', function () {
    $student = User::factory()->create(['status' => 'active']);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Jazz Improvisation Certification',
        'slug' => 'jazz-improvisation-certification',
        'status' => 'published',
        'bundle_discount_type' => 'none',
        'bundle_discount_value' => 0,
        'is_open_for_validation' => true,
        'validation_price_kes' => 800000,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'title' => 'Bebop Scales',
        'slug' => 'bebop-scales',
        'level' => 1,
        'price_kes' => 1000000,
        'order' => 1,
    ]);

    $lesson = Lesson::create([
        'module_id' => $module->id,
        'title' => 'Chromatic Approach Notes',
        'slug' => 'chromatic-approach-notes',
        'type' => 'video',
        'order' => 1,
        'is_preview' => false,
    ]);

    $assignment = Assignment::create([
        'module_id' => $module->id,
        'title' => 'Charlie Parker Solo Transcription',
        'instructions' => 'Record a transcription of Donna Lee.',
        'submission_type' => 'audio',
        'is_published' => true,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'validation',
        'status' => 'active',
        'progress_percent' => 0,
    ]);

    Sanctum::actingAs($student);

    // 1. Enrollment curriculum detail
    $curriculumRes = $this->getJson("/api/v1/student/enrollments/{$enrollment->uuid}");
    $curriculumRes->assertOk()
        ->assertJsonPath('enrollment.path_type', 'validation')
        ->assertJsonPath('enrollment.is_validation_path', true)
        ->assertJsonPath('enrollment.modules.0.lessons', []) // Lessons are hidden/empty
        ->assertJsonPath('enrollment.modules.0.assignments.0.title', 'Charlie Parker Solo Transcription'); // Assignment is present

    // 2. Direct attempt to access lesson detail returns 403 Forbidden
    $lessonRes = $this->getJson("/api/v1/student/lessons/{$lesson->uuid}");
    $lessonRes->assertStatus(403);

    // 3. Module assignments endpoint is unlocked and accessible
    $asgnRes = $this->getJson("/api/v1/student/modules/{$module->uuid}/assignments");
    $asgnRes->assertOk()
        ->assertJsonPath('assignments.0.title', 'Charlie Parker Solo Transcription');
});

test('professional passes assessments only without lessons, receives Certificate of Skills Validation, and verification page reflects path type', function () {
    $student = User::factory()->create([
        'name' => 'Elena Rostova',
        'email' => 'elena@conservatory.com',
        'status' => 'active',
    ]);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Professional Violin Virtuosity',
        'slug' => 'professional-violin-virtuosity',
        'status' => 'published',
        'bundle_discount_type' => 'none',
        'bundle_discount_value' => 0,
        'is_open_for_validation' => true,
        'validation_price_kes' => 1500000,
    ]);

    $module = Module::create([
        'course_id' => $course->id,
        'title' => 'Caprices of Paganini',
        'slug' => 'caprices-of-paganini',
        'level' => 1,
        'price_kes' => 1500000,
        'order' => 1,
    ]);

    // Add uncompleted lessons to verify they do not block validation certificate
    Lesson::create([
        'module_id' => $module->id,
        'title' => 'Caprice 24 Masterclass Part 1',
        'slug' => 'caprice-24-part-1',
        'type' => 'video',
        'order' => 1,
        'is_preview' => false,
    ]);

    $assignment = Assignment::create([
        'module_id' => $module->id,
        'title' => 'Caprice 24 Recital Submission',
        'instructions' => 'Submit full performance recording.',
        'submission_type' => 'video',
        'is_published' => true,
    ]);

    $exam = Exam::create([
        'course_id' => $course->id,
        'title' => 'Professional Violin Certification Examination',
        'description' => 'Direct skills assessment.',
        'duration_minutes' => 60,
        'is_published' => true,
    ]);

    $q1 = ExamQuestion::create([
        'exam_id' => $exam->id,
        'question_type' => 'multiple_choice',
        'prompt' => 'What violin tuning standard is standard concert pitch?',
        'points' => 10,
        'order' => 1,
        'options' => [
            ['id' => 'opt_a', 'text' => 'A = 440 Hz', 'is_correct' => true],
            ['id' => 'opt_b', 'text' => 'A = 415 Hz', 'is_correct' => false],
        ],
        'correct_answer' => 'opt_a',
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'validation',
        'status' => 'active',
        'progress_percent' => 0,
        'exam_attempt_limit' => 3,
        'exam_pass_mark_percent' => 70,
    ]);

    // 1. Mark module assignment passed
    AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'user_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'attempt_number' => 1,
        'submission_type' => 'video',
        'file_path' => 'submissions/paganini.mp4',
        'original_filename' => 'paganini.mp4',
        'file_size_bytes' => 1048576,
        'status' => 'graded',
        'score_percent' => 95,
        'passed' => true,
    ]);

    // 2. Start and pass exam
    Sanctum::actingAs($student);

    $startRes = $this->postJson("/api/v1/student/exams/{$exam->uuid}/start");
    $startRes->assertOk();
    $attemptUuid = $startRes->json('data.attempt.uuid');
    $attempt = ExamAttempt::where('uuid', $attemptUuid)->firstOrFail();

    // Submit answer
    $this->postJson("/api/v1/student/exams/attempts/{$attemptUuid}/answers", [
        'answers' => [
            [
                'question_uuid' => $q1->uuid,
                'selected_option' => 'opt_a',
            ],
        ],
    ])->assertOk();

    // Submit exam (100% score -> passed)
    $submitRes = $this->postJson("/api/v1/student/exams/attempts/{$attemptUuid}/submit");
    $submitRes->assertOk()
        ->assertJsonPath('data.attempt.status', 'graded')
        ->assertJsonPath('data.attempt.passed', true);

    $attempt->refresh();
    expect($attempt->passed)->toBeTrue();

    // 3. Verify Certificate of Skills Validation was issued
    $certificateService = app(CertificateService::class);
    $certificate = $certificateService->issueIfEligible($enrollment, $attempt);

    expect($certificate)->not->toBeNull();
    expect($certificate->path_type)->toBe('validation');
    expect($certificate->holder_name)->toBe('Elena Rostova');

    // 4. Public verification page endpoint
    $verifyRes = $this->getJson("/api/v1/certificates/verify/{$certificate->certificate_code}");
    $verifyRes->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.holder_name', 'Elena Rostova')
        ->assertJsonPath('data.course_title', 'Professional Violin Virtuosity')
        ->assertJsonPath('data.path_type', 'validation')
        ->assertJsonPath('data.is_validation_path', true)
        ->assertJsonPath('data.certificate_type_title', 'Certificate of Skills Validation')
        ->assertJsonPath('data.assessment_note', 'The holder completed assessment examination only.');
});

test('professional upgrades to full course by paying bundle price minus validation price', function () {
    $student = User::factory()->create(['status' => 'active']);
    $student->assignRole('student');

    $course = Course::create([
        'title' => 'Master Orchestration & Film Scoring',
        'slug' => 'master-orchestration-film-scoring',
        'status' => 'published',
        'bundle_discount_type' => 'none',
        'bundle_discount_value' => 0,
        'is_open_for_validation' => true,
        'validation_price_kes' => 1000000, // KES 10,000
    ]);

    // Total bundle price = 25,000 KES (2,500,000 cents)
    $m1 = Module::create([
        'course_id' => $course->id,
        'title' => 'Woodwinds & Brass',
        'slug' => 'woodwinds-brass',
        'level' => 1,
        'price_kes' => 1000000,
        'order' => 1,
    ]);

    $m2 = Module::create([
        'course_id' => $course->id,
        'title' => 'Strings & Percussion',
        'slug' => 'strings-percussion',
        'level' => 2,
        'price_kes' => 1500000,
        'order' => 2,
    ]);

    Lesson::create([
        'module_id' => $m1->id,
        'title' => 'French Horn Harmonics',
        'slug' => 'french-horn-harmonics',
        'type' => 'video',
        'order' => 1,
    ]);

    // Student has already enrolled in validation path (paid 10,000 KES)
    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'path_type' => 'validation',
        'status' => 'active',
        'progress_percent' => 0,
    ]);

    Sanctum::actingAs($student);

    // 1. Fetch course details and check upgrade pricing
    // Difference = 2,500,000 - 1,000,000 = 1,500,000 KES
    $courseRes = $this->getJson("/api/v1/courses/{$course->slug}");
    $courseRes->assertOk()
        ->assertJsonPath('course.upgrade_pricing.is_validation_path', true)
        ->assertJsonPath('course.upgrade_pricing.total_paid_kes', 1000000)
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_kes', 1500000)
        ->assertJsonPath('course.upgrade_pricing.upgrade_price_formatted', '15,000.00');

    // 2. Order bundle upgrade
    $orderRes = $this->postJson('/api/v1/orders', [
        'item_type' => 'bundle_upgrade',
        'item_id' => $course->id,
    ]);

    $orderRes->assertStatus(201)
        ->assertJsonPath('order.pricing.total_kes', 1500000)
        ->assertJsonPath('order.items.0.item_type', 'bundle_upgrade');

    $orderUuid = $orderRes->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // 3. Fulfill the upgrade order
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'card_stripe',
        'reference' => 'STRIPE-UPG-'.uniqid(),
        'amount_kes' => 1500000,
        'status' => 'pending',
    ]);

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    // 4. Verify enrollment has transitioned to standard path and all modules unlocked
    $enrollment->refresh();
    expect($enrollment->path_type)->toBe('standard');
    expect($enrollment->enrollmentModules)->toHaveCount(2);

    // 5. In curriculum, lessons are now visible and unlocked
    $curriculumRes = $this->getJson("/api/v1/student/enrollments/{$enrollment->uuid}");
    $curriculumRes->assertOk()
        ->assertJsonPath('enrollment.path_type', 'standard')
        ->assertJsonPath('enrollment.is_validation_path', false)
        ->assertJsonPath('enrollment.modules.0.lessons.0.title', 'French Horn Harmonics')
        ->assertJsonPath('enrollment.modules.0.lessons.0.is_accessible', true);
});
