<?php

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\EventBookingService;
use App\Services\EventWaitlistService;
use App\Services\OrderFulfillmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
});

test('two students compete for the last seat: first gets hold, second is blocked and redirected to waitlist', function () {
    $student1 = User::factory()->create(['status' => 'active']);
    $student1->assignRole('student');

    $student2 = User::factory()->create(['status' => 'active']);
    $student2->assignRole('student');

    // Create an event with capacity = 1
    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'masterclass',
        'title' => 'Exclusive Masterclass',
        'slug' => 'exclusive-masterclass',
        'description' => 'Single seat intimate masterclass.',
        'venue' => 'BMAS VIP Suite',
        'start_date' => now()->addDays(14),
        'end_date' => now()->addDays(15),
        'capacity' => 1,
        'training_fee_kes' => 2000000, // 20,000 KES
        'has_accommodation' => false,
        'accommodation_fee_kes' => 0,
        'status' => 'published',
    ]);

    // Student 1 books the last seat
    Sanctum::actingAs($student1);
    $response1 = $this->postJson("/api/v1/events/{$event->slug}/book", [
        'booking_type' => 'training_only',
    ]);

    $response1->assertStatus(201)
        ->assertJsonPath('booking.status', 'held')
        ->assertJsonPath('booking.hold_type', 'online')
        ->assertJsonPath('booking.total_fee_kes', 2000000);

    expect($event->fresh()->occupied_seats_count)->toBe(1)
        ->and($event->fresh()->isFull())->toBeTrue();

    // Student 2 attempts to book the same seat
    Sanctum::actingAs($student2);
    $response2 = $this->postJson("/api/v1/events/{$event->slug}/book", [
        'booking_type' => 'training_only',
    ]);

    $response2->assertStatus(422)
        ->assertJsonPath('is_full', true);

    // Student 2 joins the waitlist
    $waitlistResponse = $this->postJson("/api/v1/events/{$event->slug}/waitlist", [
        'booking_type' => 'training_only',
    ]);

    $waitlistResponse->assertStatus(201)
        ->assertJsonPath('waitlist.status', 'waiting');

    $this->assertDatabaseHas('event_waitlists', [
        'event_id' => $event->id,
        'user_id' => $student2->id,
        'status' => 'waiting',
    ]);
});

test('pricing calculations: training only vs training with accommodation', function () {
    $student1 = User::factory()->create(['status' => 'active']);
    $student1->assignRole('student');

    $student2 = User::factory()->create(['status' => 'active']);
    $student2->assignRole('student');

    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'bootcamp',
        'title' => 'Residential Vocal Intensive',
        'slug' => 'residential-vocal-intensive',
        'description' => 'Intensive with optional lodging.',
        'venue' => 'BMAS Main Complex',
        'start_date' => now()->addDays(20),
        'end_date' => now()->addDays(25),
        'capacity' => 10,
        'training_fee_kes' => 3000000, // 30,000 KES
        'has_accommodation' => true,
        'accommodation_fee_kes' => 1200000, // 12,000 KES
        'status' => 'published',
    ]);

    // Student 1 books training only
    Sanctum::actingAs($student1);
    $res1 = $this->postJson("/api/v1/events/{$event->slug}/book", [
        'booking_type' => 'training_only',
    ]);

    $res1->assertStatus(201)
        ->assertJsonPath('booking.training_fee_kes', 3000000)
        ->assertJsonPath('booking.accommodation_fee_kes', 0)
        ->assertJsonPath('booking.total_fee_kes', 3000000)
        ->assertJsonPath('order.total_kes', 3000000);

    // Student 2 books training and accommodation
    Sanctum::actingAs($student2);
    $res2 = $this->postJson("/api/v1/events/{$event->slug}/book", [
        'booking_type' => 'training_and_accommodation',
    ]);

    $res2->assertStatus(201)
        ->assertJsonPath('booking.training_fee_kes', 3000000)
        ->assertJsonPath('booking.accommodation_fee_kes', 1200000)
        ->assertJsonPath('booking.total_fee_kes', 4200000) // 42,000 KES
        ->assertJsonPath('order.total_kes', 4200000);
});

test('late payment after hold expired with full capacity marks unseated, notifies finance, and auto-refunds', function () {
    $lateStudent = User::factory()->create(['status' => 'active']);
    $lateStudent->assignRole('student');

    $competingStudent = User::factory()->create(['status' => 'active']);
    $competingStudent->assignRole('student');

    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'workshop',
        'title' => 'High Demand Piano Bootcamp',
        'slug' => 'high-demand-piano-bootcamp',
        'description' => 'Fast selling bootcamp.',
        'venue' => 'BMAS Room 4',
        'start_date' => now()->addDays(30),
        'end_date' => now()->addDays(32),
        'capacity' => 1,
        'training_fee_kes' => 1500000,
        'has_accommodation' => false,
        'accommodation_fee_kes' => 0,
        'status' => 'published',
    ]);

    // 1. Late student places an online hold
    $bookingService = app(EventBookingService::class);
    $result = $bookingService->createOnlineHold($event, $lateStudent, 'training_only');
    $lateBooking = $result['booking'];
    $lateOrder = $result['order'];

    // 2. Simulate hold expiration
    $lateBooking->update([
        'hold_expires_at' => now()->subMinutes(10),
    ]);

    // 3. Another student takes the seat and confirms it
    EventBooking::create([
        'event_id' => $event->id,
        'user_id' => $competingStudent->id,
        'booking_type' => 'training_only',
        'training_fee_kes' => 1500000,
        'accommodation_fee_kes' => 0,
        'total_fee_kes' => 1500000,
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    // 4. Now the late student's payment arrives (e.g. M-Pesa STK push callback after delay)
    $payment = Payment::create([
        'order_id' => $lateOrder->id,
        'user_id' => $lateStudent->id,
        'gateway' => 'mpesa',
        'amount_kes' => 1500000,
        'currency' => 'KES',
        'status' => 'pending',
        'transaction_reference' => 'LATE_MPESA_'.Str::random(8),
    ]);

    $fulfillmentService = app(OrderFulfillmentService::class);
    $fulfillmentService->fulfill($lateOrder, $payment);

    // 5. Verify late booking was marked unseated
    expect($lateBooking->fresh()->status)->toBe('refunded') // transitioned unseated -> refunded
        ->and($lateOrder->fresh()->refund_status)->toBe('full');

    // Verify notification was sent to finance
    $this->assertDatabaseHas('notification_logs', [
        'recipient' => 'finance@bmas.com',
        'notification_type' => 'event_unseated_alert',
    ]);

    // Verify statutory Credit Note was issued
    $this->assertDatabaseHas('credit_notes', [
        'order_id' => $lateOrder->id,
        'amount_kes' => 1500000,
    ]);
});

test('refund request inside window is approved by finance manager, releases seat, and auto-rotates next waitlist candidate', function () {
    $confirmedStudent = User::factory()->create(['status' => 'active']);
    $confirmedStudent->assignRole('student');

    $waitlistedStudent = User::factory()->create(['status' => 'active']);
    $waitlistedStudent->assignRole('student');

    $financeManager = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $financeManager->assignRole('finance_manager');

    // Event starts in 15 days (well above 7-day refund window)
    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'masterclass',
        'title' => 'Summer Strings Masterclass',
        'slug' => 'summer-strings-masterclass',
        'description' => 'Advanced orchestral technique.',
        'venue' => 'BMAS Main Auditorium',
        'start_date' => now()->addDays(15),
        'end_date' => now()->addDays(18),
        'capacity' => 1,
        'training_fee_kes' => 2500000,
        'has_accommodation' => false,
        'accommodation_fee_kes' => 0,
        'status' => 'published',
    ]);

    // Confirmed booking for student 1
    $order = Order::create([
        'user_id' => $confirmedStudent->id,
        'status' => 'paid',
        'total_kes' => 2500000,
        'vat_kes' => 0,
        'currency' => 'KES',
    ]);

    $booking = EventBooking::create([
        'event_id' => $event->id,
        'user_id' => $confirmedStudent->id,
        'order_id' => $order->id,
        'booking_type' => 'training_only',
        'training_fee_kes' => 2500000,
        'accommodation_fee_kes' => 0,
        'total_fee_kes' => 2500000,
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    $order->items()->create([
        'item_type' => 'bootcamp_booking',
        'item_id' => $booking->id,
        'title' => 'Summer Strings Masterclass',
        'price_kes' => 2500000,
        'vat_kes' => 0,
    ]);

    Payment::create([
        'order_id' => $order->id,
        'user_id' => $confirmedStudent->id,
        'gateway' => 'mpesa',
        'amount_kes' => 2500000,
        'currency' => 'KES',
        'status' => 'successful',
        'transaction_reference' => 'PAY_STRINGS_01',
    ]);

    // Student 2 is on the waitlist
    $waitlistService = app(EventWaitlistService::class);
    $waitlistEntry = $waitlistService->joinWaitlist($event, $waitlistedStudent, 'training_only');

    expect($waitlistEntry->status)->toBe('waiting');

    // Confirmed student submits a refund request with proof document
    Sanctum::actingAs($confirmedStudent);
    $proofFile = UploadedFile::fake()->create('medical_excuse.pdf', 200, 'application/pdf');

    $refundRes = $this->postJson("/api/v1/student/events/bookings/{$booking->uuid}/refund", [
        'reason' => 'Emergency medical surgery scheduled on event date.',
        'proof_document' => $proofFile,
    ]);

    $refundRes->assertStatus(201);
    $refundRequestUuid = $refundRes->json('refund_request.uuid');

    // Finance Manager reviews and approves the refund
    Sanctum::actingAs($financeManager);
    $approveRes = $this->postJson("/api/v1/finance/event-refunds/{$refundRequestUuid}/approve", [
        'manual_reference' => 'MAN-MPESA-REFUND-001',
    ]);

    $approveRes->assertStatus(200)
        ->assertJsonPath('refund_request.status', 'approved');

    // Verify booking is refunded and Credit Note was issued
    expect($booking->fresh()->status)->toBe('refunded');
    $this->assertDatabaseHas('credit_notes', [
        'order_id' => $order->id,
        'amount_kes' => 2500000,
    ]);

    // Verify waitlist auto-rotation: the freed seat was immediately offered to Student 2 with a fresh hold!
    $freshWaitlist = $waitlistEntry->fresh();
    expect($freshWaitlist->status)->toBe('offered')
        ->and($freshWaitlist->offer_expires_at)->not->toBeNull()
        ->and($freshWaitlist->held_booking_id)->not->toBeNull();

    $newHeldBooking = EventBooking::find($freshWaitlist->held_booking_id);
    expect($newHeldBooking->status)->toBe('held')
        ->and($newHeldBooking->user_id)->toBe($waitlistedStudent->id);
});

test('coordinator can list attendees, filter accommodation, check in attendee, and dispatch batch reminders', function () {
    $coordinator = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $coordinator->assignRole('workshop_coordinator');

    $student1 = User::factory()->create(['status' => 'active']);
    $student2 = User::factory()->create(['status' => 'active']);

    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'bootcamp',
        'title' => 'Choral Directors Summit',
        'slug' => 'choral-directors-summit',
        'description' => 'Summit for conductors.',
        'venue' => 'BMAS Main Hall',
        'start_date' => now()->addDays(5),
        'end_date' => now()->addDays(7),
        'capacity' => 20,
        'training_fee_kes' => 2000000,
        'has_accommodation' => true,
        'accommodation_fee_kes' => 800000,
        'status' => 'published',
    ]);

    // Booking 1: Training only
    $booking1 = EventBooking::create([
        'event_id' => $event->id,
        'user_id' => $student1->id,
        'booking_type' => 'training_only',
        'training_fee_kes' => 2000000,
        'accommodation_fee_kes' => 0,
        'total_fee_kes' => 2000000,
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    // Booking 2: Training + Accommodation
    $booking2 = EventBooking::create([
        'event_id' => $event->id,
        'user_id' => $student2->id,
        'booking_type' => 'training_and_accommodation',
        'training_fee_kes' => 2000000,
        'accommodation_fee_kes' => 800000,
        'total_fee_kes' => 2800000,
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    Sanctum::actingAs($coordinator);

    // Filter attendees by accommodation
    $attendeesRes = $this->getJson("/api/v1/coordinator/events/{$event->id}/attendees?booking_type=training_and_accommodation");
    $attendeesRes->assertStatus(200)
        ->assertJsonPath('stats.total_bookings', 1)
        ->assertJsonPath('attendees.0.booking_type', 'training_and_accommodation');

    // Check in attendee
    $checkInRes = $this->postJson("/api/v1/coordinator/events/bookings/{$booking1->uuid}/checkin");
    $checkInRes->assertStatus(200)
        ->assertJsonPath('booking.status', 'attended');

    expect($booking1->fresh()->status)->toBe('attended')
        ->and($booking1->fresh()->checked_in_by)->toBe($coordinator->id);

    // Send batch reminders
    $remindersRes = $this->postJson("/api/v1/coordinator/events/{$event->id}/reminders");
    $remindersRes->assertStatus(200)
        ->assertJsonPath('dispatched_count', 1); // booking2 is still 'confirmed'
});

test('finance staff can place a 48-hour cash hold for an in-person attendee', function () {
    $financeStaff = User::factory()->create([
        'status' => 'active',
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET_TOTP',
    ]);
    $financeStaff->assignRole('finance_staff');

    $attendee = User::factory()->create(['status' => 'active']);
    $attendee->assignRole('student');

    $event = Event::create([
        'uuid' => (string) Str::uuid(),
        'type' => 'workshop',
        'title' => 'Live Sound Engineering Bootcamp',
        'slug' => 'live-sound-engineering',
        'description' => 'Hands-on live desk mixing.',
        'venue' => 'BMAS Stage 3',
        'start_date' => now()->addDays(10),
        'end_date' => now()->addDays(12),
        'capacity' => 15,
        'training_fee_kes' => 1800000,
        'has_accommodation' => false,
        'accommodation_fee_kes' => 0,
        'status' => 'published',
    ]);

    Sanctum::actingAs($financeStaff);
    $response = $this->postJson("/api/v1/finance/events/{$event->slug}/cash-hold", [
        'attendee_id' => $attendee->id,
        'booking_type' => 'training_only',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('booking.status', 'held')
        ->assertJsonPath('booking.hold_type', 'cash')
        ->assertJsonPath('booking.total_fee_kes', 1800000);

    $booking = EventBooking::where('event_id', $event->id)->first();
    expect($booking->held_by)->toBe($financeStaff->id)
        ->and(now()->diffInHours($booking->hold_expires_at))->toBeGreaterThanOrEqual(47);
});
