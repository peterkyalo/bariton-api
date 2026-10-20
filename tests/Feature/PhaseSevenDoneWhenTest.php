<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\StudentTutoringPackage;
use App\Models\TutoringPackage;
use App\Models\TutoringSession;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use App\Services\TutoringExpiryService;
use App\Services\TutoringSlotService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->package = TutoringPackage::create([
        'title' => 'Default Tutoring Pack',
        'slug' => 'default-tutoring-pack',
        'session_count' => 5,
        'price_kes' => 2000000,
        'validity_days' => 60,
        'is_active' => true,
    ]);
});

test('student buys a tutoring package and receives session credits upon payment fulfillment', function () {
    $student = User::factory()->create(['status' => 'active']);
    $student->assignRole('student');

    $package = TutoringPackage::create([
        'title' => '5-Session Virtuoso Tutoring Pack',
        'slug' => '5-session-virtuoso-pack',
        'session_count' => 5,
        'price_kes' => 2250000,
        'validity_days' => 90,
        'is_active' => true,
    ]);

    // Student purchases via order checkout
    Sanctum::actingAs($student);
    $orderRes = $this->postJson('/api/v1/orders', [
        'item_type' => 'tutoring_package',
        'item_id' => $package->id,
    ]);

    $orderRes->assertStatus(201);
    $orderUuid = $orderRes->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    expect($order->total_kes)->toBe(2250000);

    // Simulate successful payment confirmation
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'mpesa',
        'amount_kes' => 2250000,
        'currency' => 'KES',
        'status' => 'successful',
        'transaction_reference' => 'PAY_TUTOR_'.Str::random(6),
    ]);

    $fulfillment = app(OrderFulfillmentService::class);
    $fulfillment->fulfill($order, $payment);

    // Verify student tutoring package created with 5 credits
    $this->assertDatabaseHas('student_tutoring_packages', [
        'user_id' => $student->id,
        'tutoring_package_id' => $package->id,
        'total_credits' => 5,
        'remaining_credits' => 5,
        'used_credits' => 0,
        'status' => 'active',
    ]);
});

test('instructor sets weekly availability and blocked dates; slot service calculates open slots and blocks time off', function () {
    $instructor = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $instructor->assignRole('instructor');

    Sanctum::actingAs($instructor);

    // Instructor sets recurring availability: Tuesdays (day 2) and Thursdays (day 4) from 09:00 to 12:00
    $availRes = $this->putJson('/api/v1/instructor/tutoring/availability', [
        'shifts' => [
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true],
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true],
        ],
    ]);
    $availRes->assertStatus(200);

    // Instructor blocks a specific date range (time-off/blackout)
    $nextTuesday = Carbon::now('Africa/Nairobi')->next(Carbon::TUESDAY)->toDateString();

    $blockRes = $this->postJson('/api/v1/instructor/tutoring/blocked-dates', [
        'start_date' => $nextTuesday,
        'end_date' => $nextTuesday,
        'reason' => 'Guest soloist performance out of town',
    ]);
    $blockRes->assertStatus(201);

    // Calculate slots using slot service across the next two weeks
    $slotService = app(TutoringSlotService::class);
    $slots = $slotService->getAvailableSlots(
        $instructor,
        Carbon::now('Africa/Nairobi')->startOfDay(),
        Carbon::now('Africa/Nairobi')->addDays(14)->endOfDay()
    );

    // Blocked Tuesday should have 0 slots
    $tuesdaySlots = array_filter($slots, fn ($s) => $s['date'] === $nextTuesday);
    expect($tuesdaySlots)->toBeEmpty();

    // Next Thursday should have slots
    $nextThursday = Carbon::now('Africa/Nairobi')->next(Carbon::THURSDAY)->toDateString();
    $thursdaySlots = array_filter($slots, fn ($s) => $s['date'] === $nextThursday);
    expect(count($thursdaySlots))->toBeGreaterThan(0);
});

test('student books a session and double booking is strictly prevented with locking', function () {
    $student1 = User::factory()->create(['status' => 'active']);
    $student1->assignRole('student');

    $student2 = User::factory()->create(['status' => 'active']);
    $student2->assignRole('student');

    $instructor = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $instructor->assignRole('instructor');

    $package = TutoringPackage::create([
        'title' => 'Single Session',
        'slug' => 'single-session',
        'session_count' => 1,
        'price_kes' => 500000,
        'validity_days' => 30,
        'is_active' => true,
    ]);

    // Student 1 package with 1 credit
    $student1Package = StudentTutoringPackage::create([
        'user_id' => $student1->id,
        'tutoring_package_id' => $package->id,
        'total_credits' => 1,
        'remaining_credits' => 1,
        'used_credits' => 0,
        'expires_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    // Student 2 package with 1 credit
    $student2Package = StudentTutoringPackage::create([
        'user_id' => $student2->id,
        'tutoring_package_id' => $package->id,
        'total_credits' => 1,
        'remaining_credits' => 1,
        'used_credits' => 0,
        'expires_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $slotTime = Carbon::now('Africa/Nairobi')->addDays(3)->setTime(10, 0, 0);

    // Student 1 books the slot
    Sanctum::actingAs($student1);
    $bookRes = $this->postJson('/api/v1/student/tutoring/book', [
        'student_package_uuid' => $student1Package->uuid,
        'instructor_id' => $instructor->id,
        'scheduled_start' => $slotTime->toIso8601String(),
        'mode' => 'zoom',
    ]);

    $bookRes->assertStatus(201);
    expect($student1Package->fresh()->remaining_credits)->toBe(0)
        ->and($student1Package->fresh()->status)->toBe('exhausted');

    // Student 2 attempts to book the exact same slot
    Sanctum::actingAs($student2);
    $competeRes = $this->postJson('/api/v1/student/tutoring/book', [
        'student_package_uuid' => $student2Package->uuid,
        'instructor_id' => $instructor->id,
        'scheduled_start' => $slotTime->toIso8601String(),
        'mode' => 'google_meet',
    ]);

    $competeRes->assertStatus(422)
        ->assertJsonPath('message', 'This time slot is no longer available. Another student just booked this slot.');

    // Student 2 credit was not deducted
    expect($student2Package->fresh()->remaining_credits)->toBe(1);
});

test('rescheduling: requires notice period unless emergency waiver is provided with a reason', function () {
    $student = User::factory()->create(['status' => 'active']);
    $student->assignRole('student');

    $instructor = User::factory()->create(['status' => 'active']);
    $instructor->assignRole('instructor');

    $studentPackage = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'tutoring_package_id' => $this->package->id,
        'total_credits' => 3,
        'remaining_credits' => 2,
        'used_credits' => 1,
        'expires_at' => now()->addDays(60),
        'status' => 'active',
    ]);

    // Session is scheduled in 10 hours (inside the 24-hour notice window)
    $currentStart = Carbon::now('Africa/Nairobi')->addHours(10);
    $session = TutoringSession::create([
        'student_tutoring_package_id' => $studentPackage->id,
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'scheduled_start' => $currentStart,
        'scheduled_end' => $currentStart->copy()->addHour(),
        'mode' => 'zoom',
        'status' => 'scheduled',
    ]);

    Sanctum::actingAs($student);

    $newStart = Carbon::now('Africa/Nairobi')->addDays(5)->setTime(14, 0, 0);

    // Attempt to reschedule without waiver fails due to late notice
    $failRes = $this->postJson("/api/v1/student/tutoring/sessions/{$session->uuid}/reschedule", [
        'new_scheduled_start' => $newStart->toIso8601String(),
        'waive_notice' => false,
    ]);

    $failRes->assertStatus(422);

    // Reschedule with emergency medical waiver succeeds
    $successRes = $this->postJson("/api/v1/student/tutoring/sessions/{$session->uuid}/reschedule", [
        'new_scheduled_start' => $newStart->toIso8601String(),
        'waive_notice' => true,
        'waiver_reason' => 'Severe acute vocal strain and doctor mandate for vocal rest.',
    ]);

    $successRes->assertStatus(200);
    expect($session->fresh()->notice_waived)->toBeTrue()
        ->and($session->fresh()->waiver_reason)->toContain('vocal strain');
});

test('instructor cancellation restores credit to student and alerts student via notification logs', function () {
    $student = User::factory()->create([
        'status' => 'active',
        'email' => 'student.cancel@example.com',
        'phone' => '+254711998877',
    ]);
    $student->assignRole('student');

    $instructor = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $instructor->assignRole('instructor');

    $package = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'tutoring_package_id' => $this->package->id,
        'total_credits' => 1,
        'remaining_credits' => 0,
        'used_credits' => 1,
        'expires_at' => now()->addDays(30),
        'status' => 'exhausted',
    ]);

    $session = TutoringSession::create([
        'student_tutoring_package_id' => $package->id,
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'scheduled_start' => Carbon::now('Africa/Nairobi')->addDays(2),
        'scheduled_end' => Carbon::now('Africa/Nairobi')->addDays(2)->addHour(),
        'mode' => 'zoom',
        'status' => 'scheduled',
    ]);

    Sanctum::actingAs($instructor);
    $cancelRes = $this->postJson("/api/v1/instructor/tutoring/sessions/{$session->uuid}/cancel", [
        'reason' => 'Emergency conservatory faculty meeting called by the Director.',
    ]);

    $cancelRes->assertStatus(200);

    // Verify session status and credit restoration
    expect($session->fresh()->status)->toBe('cancelled_by_instructor');
    expect($package->fresh()->remaining_credits)->toBe(1)
        ->and($package->fresh()->status)->toBe('active');

    // Verify notification was dispatched to student
    $this->assertDatabaseHas('notification_logs', [
        'recipient' => 'student.cancel@example.com',
        'notification_type' => 'tutoring_session_cancelled_by_instructor',
    ]);
});

test('tutoring expiry service automatically expires elapsed packages', function () {
    $student = User::factory()->create(['status' => 'active']);

    $expiredPackage = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'tutoring_package_id' => $this->package->id,
        'total_credits' => 5,
        'remaining_credits' => 3,
        'used_credits' => 2,
        'expires_at' => now()->subDay(), // Elapsed
        'status' => 'active',
    ]);

    $validPackage = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'tutoring_package_id' => $this->package->id,
        'total_credits' => 5,
        'remaining_credits' => 5,
        'used_credits' => 0,
        'expires_at' => now()->addDays(30),
        'status' => 'active',
    ]);

    $expiryService = app(TutoringExpiryService::class);
    $count = $expiryService->expireUnusedPackages();

    expect($count)->toBe(1)
        ->and($expiredPackage->fresh()->status)->toBe('expired')
        ->and($validPackage->fresh()->status)->toBe('active');
});

test('instructor marks session attendance as attended or missed with instructor notes', function () {
    $student = User::factory()->create(['status' => 'active']);
    $instructor = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $instructor->assignRole('instructor');

    $studentPackage = StudentTutoringPackage::create([
        'user_id' => $student->id,
        'tutoring_package_id' => $this->package->id,
        'total_credits' => 1,
        'remaining_credits' => 0,
        'used_credits' => 1,
        'expires_at' => now()->addDays(30),
        'status' => 'exhausted',
    ]);

    $session = TutoringSession::create([
        'student_tutoring_package_id' => $studentPackage->id,
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'scheduled_start' => Carbon::now('Africa/Nairobi')->subHours(2),
        'scheduled_end' => Carbon::now('Africa/Nairobi')->subHour(),
        'mode' => 'in_person',
        'location' => 'BMAS Studio Hall B',
        'status' => 'scheduled',
    ]);

    Sanctum::actingAs($instructor);

    $attendRes = $this->postJson("/api/v1/instructor/tutoring/sessions/{$session->uuid}/attendance", [
        'status' => 'attended',
        'notes' => 'Great work on Chopin Ballade No. 1 coda octaves. Focus on left-hand relaxation for next week.',
    ]);

    $attendRes->assertStatus(200);
    expect($session->fresh()->status)->toBe('attended')
        ->and($session->fresh()->instructor_notes)->toContain('Chopin Ballade');
});
