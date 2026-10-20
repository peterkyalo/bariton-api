<?php

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Exam;
use App\Models\InstructorAvailability;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StudentTutoringPackage;
use App\Models\TutoringPackage;
use App\Models\User;
use App\Services\NotificationTemplateService;
use App\Services\OrderFulfillmentService;
use Carbon\Carbon;
use Database\Seeders\CoursesSeeder;
use Database\Seeders\EventSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TutoringPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->seed(NotificationTemplateSeeder::class);
    $this->seed(CoursesSeeder::class);
    $this->seed(EventSeeder::class);
    $this->seed(TutoringPackageSeeder::class);

    Storage::fake('local');
    Storage::fake('public');
});

test('complete end-to-end platform verification walkthrough across all 8 phases', function () {
    Mail::fake();

    // -------------------------------------------------------------------------
    // STEP 1: User Registration & Authentication (Phase 1)
    // -------------------------------------------------------------------------
    $registrationPayload = [
        'name' => 'Wanjiku Kamau',
        'email' => 'wanjiku.kamau@example.com',
        'phone' => '+254712345678',
        'date_of_birth' => '1998-05-14',
        'password' => 'ConservatoryPass123!',
        'password_confirmation' => 'ConservatoryPass123!',
        'privacy_consent' => true,
    ];

    $regResponse = $this->postJson('/api/v1/auth/register', $registrationPayload);
    $regResponse->assertStatus(201);

    $student = User::where('email', 'wanjiku.kamau@example.com')->firstOrFail();
    expect($student->hasRole('student'))->toBe(true);

    // -------------------------------------------------------------------------
    // STEP 2: Course Enrollment & M-Pesa STK Payment (Phase 2 & 3a)
    // -------------------------------------------------------------------------
    $course = Course::where('slug', 'classical-piano-mastery')->firstOrFail();

    // Order creation for full course bundle
    Sanctum::actingAs($student);
    $orderResponse = $this->postJson('/api/v1/orders', [
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
    ]);
    $orderResponse->assertStatus(201);
    $orderUuid = $orderResponse->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // Simulate M-Pesa STK Push Confirmation
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'mpesa',
        'currency' => 'KES',
        'reference' => 'WS999TESTPAY',
        'amount_kes' => $order->total_kes,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    app(OrderFulfillmentService::class)->fulfill($order, $payment);
    $order->refresh();
    expect($order->status)->toBe('paid');
    expect($order->receipt)->not->toBeNull();
    expect($order->receipt->receipt_number)->toStartWith('BMAS-RCT-');

    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->firstOrFail();
    expect($enrollment->path_type)->toBe('standard');

    // -------------------------------------------------------------------------
    // STEP 3: Progress Recording & Assignment Rubric Evaluation (Phase 4a)
    // -------------------------------------------------------------------------
    $firstLesson = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))->firstOrFail();
    $progressRes = $this->postJson("/api/v1/student/lessons/{$firstLesson->uuid}/progress", [
        'progress_percent' => 100,
        'completed' => true,
    ]);
    $progressRes->assertStatus(200);

    // Instructor user with 2FA
    $instructor = User::factory()->create([
        'name' => 'Maestro David Ochieng',
        'email' => 'maestro.david@bmas.com',
        'two_factor_secret' => 'INSTR2FASECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $instructor->assignRole('instructor');
    $course->instructors()->attach($instructor->id);

    // Create assignment and submission
    $firstModule = $firstLesson->module;
    $assignment = Assignment::create([
        'module_id' => $firstModule->id,
        'title' => 'C Major Cadence & Polyphonic Etude',
        'slug' => 'c-major-cadence-etude',
        'uuid' => (string) Str::uuid(),
        'instructions' => 'Record a steady performance of the cadence.',
        'submission_type' => 'video',
        'is_published' => true,
    ]);

    $submission = AssignmentSubmission::create([
        'assignment_id' => $assignment->id,
        'enrollment_id' => $enrollment->id,
        'user_id' => $student->id,
        'uuid' => (string) Str::uuid(),
        'attempt_number' => 1,
        'file_path' => 'submissions/test/video.mp4',
        'original_filename' => 'video.mp4',
        'file_size_bytes' => 150000000,
        'media_type' => 'video',
        'status' => 'submitted',
    ]);
    Storage::disk('local')->put('submissions/test/video.mp4', 'dummy_piano_stream');

    // Instructor marks submission
    Sanctum::actingAs($instructor);
    $gradeRes = $this->postJson("/api/v1/instructor/grading/{$submission->uuid}/grade", [
        'total_points_earned' => 88,
        'total_points_possible' => 100,
        'feedback' => 'Superb dynamic control, clear pedal release, and confident articulation.',
    ]);
    $gradeRes->assertStatus(200);
    $submission->refresh();
    expect($submission->status)->toBe('graded');
    expect($submission->passed)->toBe(true);

    // -------------------------------------------------------------------------
    // STEP 4: Final Certification Examination & Tamper-Proof Certificate (Phase 4b)
    // -------------------------------------------------------------------------
    $exam = Exam::create([
        'course_id' => $course->id,
        'title' => 'Classical Piano Final Conservatory Exam',
        'uuid' => (string) Str::uuid(),
        'duration_minutes' => 60,
        'is_active' => true,
    ]);

    $cert = Certificate::create([
        'enrollment_id' => $enrollment->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'certificate_code' => 'BMAS-CERT-2026-WK99',
        'uuid' => (string) Str::uuid(),
        'holder_name' => $student->name,
        'course_title' => $course->title,
        'issued_at' => now(),
        'status' => 'issued',
        'path_type' => 'standard',
    ]);

    // Public verification check (unauthenticated rate-limited endpoint)
    $verifyRes = $this->getJson("/api/v1/certificates/verify/{$cert->certificate_code}");
    $verifyRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.certificate_code', 'BMAS-CERT-2026-WK99')
        ->assertJsonPath('data.holder_name', 'Wanjiku Kamau')
        ->assertJsonPath('data.course_title', $course->title);

    // -------------------------------------------------------------------------
    // STEP 5: Physical Bootcamp Seat Hold & Attendance Check-In (Phase 6)
    // -------------------------------------------------------------------------
    $event = Event::where('slug', 'nairobi-international-jazz-masterclass')->firstOrFail();
    $coordinator = User::factory()->create([
        'two_factor_secret' => 'COORD2FASECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $coordinator->assignRole('workshop_coordinator');

    // Online seat hold by student
    Sanctum::actingAs($student);
    $holdRes = $this->postJson("/api/v1/events/{$event->slug}/book", [
        'booking_type' => 'training_only',
    ]);
    $holdRes->assertStatus(201);
    $bookingUuid = $holdRes->json('booking.uuid');

    // Simulate payment completion
    $booking = EventBooking::where('uuid', $bookingUuid)->firstOrFail();
    $booking->update([
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    // Coordinator check-in on event day
    Sanctum::actingAs($coordinator);
    $checkInRes = $this->postJson("/api/v1/coordinator/events/bookings/{$booking->uuid}/checkin");
    $checkInRes->assertStatus(200);
    $booking->refresh();
    expect($booking->status)->toBe('attended');

    // -------------------------------------------------------------------------
    // STEP 6: One-on-One Tutoring: Booking & Instructor Cancellation (Phase 7)
    // -------------------------------------------------------------------------
    $tutoringPkg = TutoringPackage::where('slug', 'standard-virtuoso-coaching-bundle')->firstOrFail();

    // Student has 5 credits
    $studentPkg = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'order_id' => $order->id,
        'tutoring_package_id' => $tutoringPkg->id,
        'total_credits' => 5,
        'used_credits' => 0,
        'remaining_credits' => 5,
        'expires_at' => now()->addDays(90),
        'status' => 'active',
    ]);

    // Configure instructor weekly availability
    $slotTime = Carbon::now('Africa/Nairobi')->addDays(3)->setTime(10, 0, 0);
    InstructorAvailability::create([
        'instructor_id' => $instructor->id,
        'day_of_week' => $slotTime->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_active' => true,
    ]);

    // Student books tutoring session
    Sanctum::actingAs($student);
    $bookSessionRes = $this->postJson('/api/v1/student/tutoring/book', [
        'student_package_uuid' => $studentPkg->uuid,
        'instructor_id' => $instructor->id,
        'course_id' => $course->id,
        'scheduled_start' => $slotTime->toIso8601String(),
        'mode' => 'zoom',
    ]);
    $bookSessionRes->assertStatus(201);
    $studentPkg->refresh();
    expect($studentPkg->remaining_credits)->toBe(4);
    expect($studentPkg->used_credits)->toBe(1);

    $sessionUuid = $bookSessionRes->json('session.uuid');

    // Instructor cancels due to studio maintenance -> credit refunded automatically!
    Sanctum::actingAs($instructor);
    $cancelRes = $this->postJson("/api/v1/instructor/tutoring/sessions/{$sessionUuid}/cancel", [
        'reason' => 'Emergency acoustic maintenance in Piano Studio 2',
    ]);
    $cancelRes->assertStatus(200);
    $studentPkg->refresh();
    expect($studentPkg->remaining_credits)->toBe(5); // Refunded!
    expect($studentPkg->used_credits)->toBe(0);

    // -------------------------------------------------------------------------
    // STEP 7: Automated Notifications with Variable Interpolation (Phase 8)
    // -------------------------------------------------------------------------
    /** @var NotificationTemplateService $notifService */
    $notifService = app(NotificationTemplateService::class);
    $dispatchResult = $notifService->send('certificate_awarded', $student, [
        'course_title' => $course->title,
        'certificate_number' => $cert->certificate_code,
        'verification_code' => $cert->certificate_code,
        'verification_url' => "https://app.bmas.com/verify/{$cert->certificate_code}",
    ]);
    expect($dispatchResult['status'])->toBe('dispatched');

    // -------------------------------------------------------------------------
    // STEP 8: Multi-Role Dashboards & CSV Reporting (Phase 8)
    // -------------------------------------------------------------------------
    $admin = User::factory()->create([
        'two_factor_secret' => 'ADMIN2FASECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    // Admin Dashboard check
    Sanctum::actingAs($admin);
    $adminDashRes = $this->getJson('/api/v1/dashboards/admin');
    $adminDashRes->assertStatus(200);
    expect($adminDashRes->json('revenue.total_kes'))->toBeGreaterThan(0);
    expect($adminDashRes->json('enrollments.total'))->toBeGreaterThan(0);

    // Admin CSV download
    $adminCsvRes = $this->get('/api/v1/dashboards/admin/export-csv');
    $adminCsvRes->assertStatus(200);
    expect($adminCsvRes->headers->get('Content-Type'))->toContain('text/csv');

    // Health check endpoint check
    $healthRes = $this->getJson('/api/v1/health');
    $healthRes->assertStatus(200)->assertJsonPath('status', 'healthy');
});
