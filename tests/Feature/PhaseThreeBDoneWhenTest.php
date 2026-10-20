<?php

use App\Models\Course;
use App\Models\CreditNote;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

function createTestCourseWithTwoModules(): Course
{
    $course = Course::create([
        'title' => 'Mastering Classical Viola',
        'slug' => 'mastering-classical-viola',
        'description' => 'Comprehensive viola conservatory course.',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 10,
    ]);

    $course->modules()->create([
        'title' => 'Viola Clef & Bowing Fundamentals',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 400000, // 4,000 KES
    ]);

    $course->modules()->create([
        'title' => 'Intermediate Viola Vibrato & Shifts',
        'level' => 'intermediate',
        'order' => 2,
        'price_kes' => 600000, // 6,000 KES
    ]);

    return $course;
}

test('card payments complete in test mode via stripe webhook', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithTwoModules();

    // 1. Student creates order
    $orderRes = $this->actingAs($student)->postJson('/api/v1/orders', [
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
    ]);

    $orderRes->assertCreated();
    $orderUuid = $orderRes->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // 2. Student initiates Stripe payment
    $stripeRes = $this->actingAs($student)->postJson("/api/v1/orders/{$orderUuid}/pay/stripe");

    $stripeRes->assertOk()
        ->assertJsonStructure(['client_secret', 'payment_intent_id']);

    $piId = $stripeRes->json('payment_intent_id');
    $payment = Payment::where('stripe_payment_intent_id', $piId)->firstOrFail();
    expect($payment->status)->toBe('pending');

    // 3. Webhook arrives from Stripe (payment_intent.succeeded)
    $webhookPayload = [
        'id' => 'evt_test_'.Str::random(16),
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => $piId,
                'status' => 'succeeded',
                'amount' => $order->total_kes,
                'currency' => 'kes',
            ],
        ],
    ];

    $webhookRes = $this->postJson('/api/v1/webhooks/stripe', $webhookPayload);
    $webhookRes->assertOk()->assertJson(['received' => true]);

    // 4. Verify order paid, payment successful, enrollment active
    $order->refresh();
    $payment->refresh();

    expect($order->status)->toBe('paid');
    expect($payment->status)->toBe('successful');

    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe('active');

    // Receipt issued
    expect($order->receipt)->not->toBeNull();
});

test('paypal payments complete in test mode via capture', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithTwoModules();

    // 1. Student creates order
    $orderRes = $this->actingAs($student)->postJson('/api/v1/orders', [
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
    ]);

    $orderRes->assertCreated();
    $orderUuid = $orderRes->json('order.uuid');

    // 2. Student initiates PayPal payment
    $paypalRes = $this->actingAs($student)->postJson("/api/v1/orders/{$orderUuid}/pay/paypal");

    $paypalRes->assertOk()
        ->assertJsonStructure(['paypal_order_id', 'approve_link', 'amount_usd']);

    $paypalOrderId = $paypalRes->json('paypal_order_id');

    // 3. Student captures PayPal payment upon approval
    $captureRes = $this->actingAs($student)->postJson("/api/v1/orders/{$orderUuid}/pay/paypal/capture", [
        'id' => 'CAPTURE_'.Str::random(10),
        'status' => 'COMPLETED',
        'paypal_order_id' => $paypalOrderId,
    ]);

    $captureRes->assertOk()
        ->assertJson(['order_status' => 'paid']);

    $order = Order::where('uuid', $orderUuid)->firstOrFail();
    expect($order->status)->toBe('paid');

    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe('active');
});

test('a refund on one item moves from request to approval to credit note to revoked access for that item only', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $financeManager = User::factory()->create([
        'two_factor_secret' => 'TESTSECRETKEY123',
        'two_factor_confirmed_at' => now(),
    ]);
    $financeManager->assignRole('finance_manager');

    $course = createTestCourseWithTwoModules();
    $module1 = $course->modules()->where('order', 1)->first();
    $module2 = $course->modules()->where('order', 2)->first();

    // Student places order for both modules separately (2 items)
    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'paid',
        'total_kes' => 1000000,
        'vat_kes' => 137931,
        'total_usd_cents' => 7693,
        'paid_at' => now(),
        'refund_window_days' => 14,
        'currency' => 'KES',
    ]);

    $item1 = $order->items()->create([
        'item_type' => 'module',
        'item_id' => $module1->id,
        'title' => $module1->title,
        'price_kes' => 400000,
        'meta' => ['course_id' => $course->id, 'module_id' => $module1->id],
    ]);

    $item2 = $order->items()->create([
        'item_type' => 'module',
        'item_id' => $module2->id,
        'title' => $module2->title,
        'price_kes' => 600000,
        'meta' => ['course_id' => $course->id, 'module_id' => $module2->id],
    ]);

    Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'stripe',
        'amount_kes' => 1000000,
        'status' => 'successful',
        'stripe_payment_intent_id' => 'pi_test_refund_flow',
        'reference' => 'ch_test_123',
    ]);

    // Active enrollment with both modules unlocked
    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'progress_percent' => 10.00, // under 50%
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module1->id,
        'amount_paid_kes' => 400000,
    ]);

    EnrollmentModule::create([
        'enrollment_id' => $enrollment->id,
        'module_id' => $module2->id,
        'amount_paid_kes' => 600000,
    ]);

    // 1. Student requests refund for item 2 (Module 2) only
    $reqRes = $this->actingAs($student)->postJson("/api/v1/student/orders/{$order->uuid}/refund-requests", [
        'order_item_id' => $item2->id,
        'reason' => 'I would like to focus solely on Level 1 right now.',
    ]);

    $reqRes->assertCreated()
        ->assertJsonPath('refund_request.status', 'pending');

    $refundUuid = $reqRes->json('refund_request.uuid');
    $refund = RefundRequest::where('uuid', $refundUuid)->firstOrFail();
    expect($refund->amount_kes)->toBe(600000);

    // 2. Finance Manager approves the refund
    $reviewRes = $this->actingAs($financeManager)->postJson("/api/v1/finance/refund-requests/{$refundUuid}/review", [
        'action' => 'approve',
        'notes' => 'Approved per 14-day policy.',
    ]);

    $reviewRes->dump();
    $reviewRes->assertOk()
        ->assertJsonPath('refund_request.status', 'approved')
        ->assertJsonStructure(['credit_note' => ['credit_note_number']]);

    // 3. Verify Credit Note was issued with sequential number
    $creditNote = CreditNote::where('refund_request_id', $refund->id)->first();
    expect($creditNote)->not->toBeNull();
    expect($creditNote->credit_note_number)->toStartWith('BMAS-CRN-');
    expect($creditNote->amount_kes)->toBe(600000);

    // 4. Verify access revoked for item 2 ONLY; item 1 remains unlocked!
    $module1Access = EnrollmentModule::where('enrollment_id', $enrollment->id)
        ->where('module_id', $module1->id)
        ->exists();

    $module2Access = EnrollmentModule::where('enrollment_id', $enrollment->id)
        ->where('module_id', $module2->id)
        ->exists();

    expect($module1Access)->toBeTrue('Student should keep access to Module 1');
    expect($module2Access)->toBeFalse('Access to Module 2 should be revoked');

    $order->refresh();
    expect($order->refund_status)->toBe('partial');
});

test('a refund request after a certificate is blocked', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithTwoModules();

    // Ensure certificates table exists to test blocking constraint
    if (! Schema::hasTable('certificates')) {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id');
            $table->foreignId('course_id');
            $table->string('status')->default('issued');
            $table->timestamps();
        });
    }

    // Insert an issued certificate for the student in this course
    DB::table('certificates')->insert([
        'uuid' => (string) Str::uuid(),
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'issued',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'paid',
        'total_kes' => 900000,
        'paid_at' => now(),
        'refund_window_days' => 14,
        'currency' => 'KES',
    ]);

    $item = $order->items()->create([
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
        'title' => $course->title,
        'price_kes' => 900000,
    ]);

    // Student attempts to request a refund
    $response = $this->actingAs($student)->postJson("/api/v1/student/orders/{$order->uuid}/refund-requests", [
        'order_item_id' => $item->id,
        'reason' => 'I finished the course and now want my money back.',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Refunds are blocked because an official completion certificate has already been issued for this course.']);

    // Ensure no refund request was created
    expect(RefundRequest::count())->toBe(0);
});

test('a refund request after progress exceeds 50 percent is blocked', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithTwoModules();

    // Student has 75% course progress (exceeds 50% limit)
    Enrollment::create([
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'progress_percent' => 75.00,
    ]);

    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'paid',
        'total_kes' => 900000,
        'paid_at' => now(),
        'refund_window_days' => 14,
        'currency' => 'KES',
    ]);

    $item = $order->items()->create([
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
        'title' => $course->title,
        'price_kes' => 900000,
    ]);

    $response = $this->actingAs($student)->postJson("/api/v1/student/orders/{$order->uuid}/refund-requests", [
        'order_item_id' => $item->id,
        'reason' => 'Changed my mind after taking most of the lessons.',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Refunds are not permitted once course progress reaches or exceeds 50%.']);
});
