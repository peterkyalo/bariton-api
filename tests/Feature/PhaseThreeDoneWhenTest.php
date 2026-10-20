<?php

use App\Jobs\QueryPendingMpesaPaymentsJob;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentModule;
use App\Models\Module;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use App\Services\Payments\MpesaPaymentGateway;
use App\Services\Receipts\ReceiptIssuerInterface;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Storage::fake('local');
});

function createTestCourseWithModules(): Course
{
    $course = Course::create([
        'title' => 'Concert Cello Mastery',
        'slug' => 'concert-cello-mastery',
        'description' => 'Master cello from basics to virtuoso.',
        'status' => 'published',
        'bundle_discount_type' => 'percent',
        'bundle_discount_value' => 10,
    ]);

    $course->modules()->create([
        'title' => 'Beginner Posture & Bowing',
        'level' => 'beginner',
        'order' => 1,
        'price_kes' => 300000,
    ]);

    $course->modules()->create([
        'title' => 'Intermediate Shifts & Vibrato',
        'level' => 'intermediate',
        'order' => 2,
        'price_kes' => 500000,
    ]);

    return $course;
}

test('an mpesa sandbox payment completes via callback, grants access, and issues receipt', function () {
    $student = User::factory()->create([
        'phone' => '0712345678',
    ]);
    $student->assignRole('student');

    $course = createTestCourseWithModules();

    // 1. Student creates an order for the full course bundle
    $orderResponse = $this->actingAs($student)->postJson('/api/v1/orders', [
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
    ]);

    $orderResponse->assertCreated()
        ->assertJsonPath('order.status', 'pending');

    $orderUuid = $orderResponse->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // Verify access is NOT yet granted before payment
    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment)->toBeNull();

    // 2. Student initiates M-Pesa STK push
    $initResponse = $this->actingAs($student)->postJson("/api/v1/orders/{$orderUuid}/pay/mpesa", [
        'phone' => '0712345678',
    ]);

    $initResponse->assertOk()
        ->assertJsonStructure(['checkout_request_id', 'payment']);

    $checkoutRequestId = $initResponse->json('checkout_request_id');
    $payment = Payment::where('checkout_request_id', $checkoutRequestId)->firstOrFail();
    expect($payment->status)->toBe('pending');

    // 3. Daraja webhook callback arrives with valid callback token and success ResultCode (0)
    $token = config('services.mpesa.callback_token');
    $callbackPayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'MR_TEST_1234',
                'CheckoutRequestID' => $checkoutRequestId,
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 7200],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'QJH1234567'],
                        ['Name' => 'TransactionDate', 'Value' => 20261020120000],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ];

    $callbackResponse = $this->postJson("/api/v1/payments/mpesa/callback/{$token}", $callbackPayload);

    $callbackResponse->assertOk()
        ->assertJson(['ResultCode' => 0]);

    // 4. Verify order is marked paid and payment successful
    $order->refresh();
    $payment->refresh();

    expect($order->status)->toBe('paid');
    expect($order->paid_at)->not->toBeNull();
    expect($payment->status)->toBe('successful');
    expect($payment->reference)->toBe('QJH1234567');

    // 5. Verify access is granted (active enrollment and all modules unlocked)
    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe('active');

    $unlockedModules = EnrollmentModule::where('enrollment_id', $enrollment->id)->get();
    expect($unlockedModules)->toHaveCount(2);

    // 6. Verify sequential receipt is issued
    $receipt = Receipt::where('order_id', $order->id)->first();
    expect($receipt)->not->toBeNull();
    expect($receipt->receipt_number)->toStartWith('BMAS-RCT-');
    expect($receipt->status)->toBe('issued');
});

test('query job confirms mpesa payment when callback never arrives', function () {
    // Configure mock credentials so Daraja HTTP queries are made
    config([
        'services.mpesa.consumer_key' => 'mock_consumer_key',
        'services.mpesa.consumer_secret' => 'mock_consumer_secret',
        'services.mpesa.shortcode' => '174379',
    ]);

    $student = User::factory()->create(['phone' => '0722000000']);
    $student->assignRole('student');

    $course = createTestCourseWithModules();

    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'pending',
        'total_kes' => 720000,
        'vat_kes' => 99310,
        'vat_rate_percent' => 16.00,
        'exchange_rate_used' => 130.00,
        'total_usd_cents' => 5538,
        'currency' => 'KES',
        'expires_at' => now()->addMinutes(30),
    ]);

    $order->items()->create([
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
        'title' => $course->title,
        'price_kes' => 720000,
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'mpesa',
        'amount_kes' => 720000,
        'status' => 'pending',
        'checkout_request_id' => 'ws_CO_TEST_NEVER_CALLBACK_9999',
        'created_at' => now()->subMinutes(5),
    ]);

    // Mock Daraja OAuth token and STK push query response
    Http::fake([
        '*/oauth/v1/generate*' => Http::response(['access_token' => 'daraja_mock_access_token'], 200),
        '*/mpesa/stkpushquery/v1/query' => Http::response([
            'ResponseCode' => '0',
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'MerchantRequestID' => 'MR_TEST',
            'CheckoutRequestID' => 'ws_CO_TEST_NEVER_CALLBACK_9999',
        ], 200),
    ]);

    // Run background query job
    $job = new QueryPendingMpesaPaymentsJob;
    $job->handle(app(MpesaPaymentGateway::class), app(OrderFulfillmentService::class));

    $order->refresh();
    $payment->refresh();

    expect($payment->status)->toBe('successful');
    expect($order->status)->toBe('paid');

    // Access granted
    $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment)->not->toBeNull();
    expect($enrollment->status)->toBe('active');

    // Receipt issued
    $receipt = Receipt::where('order_id', $order->id)->first();
    expect($receipt)->not->toBeNull();
});

test('a cash receipt appears only after finance records payment', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $finance = User::factory()->create([
        'two_factor_secret' => 'TESTSECRETKEY123',
        'two_factor_confirmed_at' => now(),
    ]);
    $finance->assignRole('finance_staff');

    $course = createTestCourseWithModules();

    // Student places order with cash method
    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'pending',
        'payment_method' => 'cash',
        'total_kes' => 720000,
        'vat_kes' => 99310,
        'vat_rate_percent' => 16.00,
        'total_usd_cents' => 5538,
        'currency' => 'KES',
        'expires_at' => now()->addMinutes(30),
    ]);

    $order->items()->create([
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
        'title' => $course->title,
        'price_kes' => 720000,
    ]);

    // Student checks orders list - order has no receipt yet
    $studentOrders = $this->actingAs($student)->getJson('/api/v1/student/orders');
    $studentOrders->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.receipt', null);

    // Finance records the cash payment
    $recordResponse = $this->actingAs($finance)->postJson('/api/v1/finance/cash-payment', [
        'order_id' => $order->id,
        'amount_kes' => 720000,
        'payer_name' => 'Alice Parent',
        'notes' => 'Received at school main bursar desk.',
    ]);

    $recordResponse->assertOk()
        ->assertJsonPath('order.status', 'paid')
        ->assertJsonStructure(['receipt' => ['receipt_number']]);

    // Receipt now exists
    $receipt = Receipt::where('order_id', $order->id)->first();
    expect($receipt)->not->toBeNull();
    expect($receipt->amount_kes)->toBe(720000);

    // Student can now see receipt in their orders
    $studentOrdersAfter = $this->actingAs($student)->getJson('/api/v1/student/orders');
    $studentOrdersAfter->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'paid')
        ->assertJsonPath('data.0.receipt.receipt_number', $receipt->receipt_number);
});

test('two payments confirmed receive consecutive receipt numbers with no gaps', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('student');

    $user2 = User::factory()->create();
    $user2->assignRole('student');

    $order1 = Order::create([
        'user_id' => $user1->id,
        'status' => 'pending',
        'total_kes' => 300000,
        'vat_kes' => 41379,
        'total_usd_cents' => 2308,
        'currency' => 'KES',
    ]);

    $order2 = Order::create([
        'user_id' => $user2->id,
        'status' => 'pending',
        'total_kes' => 500000,
        'vat_kes' => 68966,
        'total_usd_cents' => 3846,
        'currency' => 'KES',
    ]);

    $payment1 = Payment::create([
        'order_id' => $order1->id,
        'user_id' => $user1->id,
        'gateway' => 'mpesa',
        'amount_kes' => 300000,
        'status' => 'successful',
        'reference' => 'PAY1',
    ]);

    $payment2 = Payment::create([
        'order_id' => $order2->id,
        'user_id' => $user2->id,
        'gateway' => 'cash',
        'amount_kes' => 500000,
        'status' => 'successful',
        'reference' => 'PAY2',
    ]);

    $issuer = app(ReceiptIssuerInterface::class);

    // Issue receipt for payment 1 inside transaction
    $receipt1 = DB::transaction(fn () => $issuer->issue($payment1));

    // Issue receipt for payment 2 inside transaction
    $receipt2 = DB::transaction(fn () => $issuer->issue($payment2));

    expect($receipt1->receipt_number)->toBe('BMAS-RCT-000001');
    expect($receipt2->receipt_number)->toBe('BMAS-RCT-000002');
});

test('access is granted only after an order is marked paid', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithModules();
    $module1 = $course->modules()->where('order', 1)->first();

    // 1. Student places order for module 1
    $order = Order::create([
        'user_id' => $student->id,
        'status' => 'pending',
        'total_kes' => 300000,
        'vat_kes' => 41379,
        'total_usd_cents' => 2308,
        'currency' => 'KES',
    ]);

    $order->items()->create([
        'item_type' => 'module',
        'item_id' => $module1->id,
        'title' => $module1->title,
        'price_kes' => 300000,
    ]);

    // Check module unlocked before payment
    $hasAccess = EnrollmentModule::whereHas('enrollment', fn ($q) => $q->where('user_id', $student->id))
        ->where('module_id', $module1->id)
        ->exists();
    expect($hasAccess)->toBeFalse();

    // 2. Fulfill payment
    $payment = Payment::create([
        'order_id' => $order->id,
        'user_id' => $student->id,
        'gateway' => 'cash',
        'amount_kes' => 300000,
        'status' => 'pending',
    ]);

    app(OrderFulfillmentService::class)->fulfill($order, $payment);

    // Check module unlocked after payment
    $hasAccessNow = EnrollmentModule::whereHas('enrollment', fn ($q) => $q->where('user_id', $student->id))
        ->where('module_id', $module1->id)
        ->exists();
    expect($hasAccessNow)->toBeTrue();
});

test('unpaid orders expire after the duration set in settings', function () {
    // Setting order_expiry_minutes = 30
    Setting::updateOrCreate(
        ['key' => 'order_expiry_minutes'],
        ['value' => 30, 'type' => 'integer', 'group' => 'orders']
    );
    app(SettingsService::class)->clearCache();

    $student = User::factory()->create();
    $student->assignRole('student');

    $course = createTestCourseWithModules();

    // Create order via API to trigger expiry calculation
    $response = $this->actingAs($student)->postJson('/api/v1/orders', [
        'item_type' => 'course_bundle',
        'item_id' => $course->id,
    ]);

    $response->assertCreated();
    $orderUuid = $response->json('order.uuid');
    $order = Order::where('uuid', $orderUuid)->firstOrFail();

    // Currently not expired
    expect($order->isExpired())->toBeFalse();

    // Travel 31 minutes into the future
    $this->travel(31)->minutes();

    expect($order->isExpired())->toBeTrue();

    // Attempting to initiate M-Pesa on expired order returns 400
    $paymentAttempt = $this->actingAs($student)->postJson("/api/v1/orders/{$orderUuid}/pay/mpesa", [
        'phone' => '0712345678',
    ]);

    $paymentAttempt->assertStatus(400)
        ->assertJson(['message' => 'This order has expired. Please initiate a new order.']);

    $order->refresh();
    expect($order->status)->toBe('expired');
});
