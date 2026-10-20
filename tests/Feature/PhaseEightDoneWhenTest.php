<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationTemplateService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    Artisan::call('db:seed', ['--class' => 'SettingsSeeder']);
    Artisan::call('db:seed', ['--class' => 'NotificationTemplateSeeder']);
});

test('health check endpoint returns system status with database, cache, and storage ok', function () {
    $res = $this->getJson('/api/v1/health');

    $res->assertStatus(200)
        ->assertJson([
            'status' => 'healthy',
            'app_version' => '1.0.0',
        ])
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.cache.status', 'ok')
        ->assertJsonPath('checks.storage.status', 'ok');
});

test('notification template service interpolates variables and logs email and sms dispatch', function () {
    Mail::fake();

    $student = User::factory()->create([
        'name' => 'Kipchoge Keino',
        'email' => 'kipchoge@example.com',
        'phone' => '+254712999888',
    ]);
    $student->assignRole('student');

    /** @var NotificationTemplateService $service */
    $service = app(NotificationTemplateService::class);

    $res = $service->send('payment_received', $student, [
        'amount_formatted' => 'KES 25,000',
        'payment_method' => 'M-Pesa STK Push',
        'payment_reference' => 'TEST-REF-999',
        'order_reference' => 'ORD-1234',
    ]);

    expect($res['status'])->toBe('dispatched');
    expect($res['channels']['email'])->toBe('sent');
    expect($res['channels']['sms'])->toBe('sent');

    $this->assertDatabaseHas('notification_logs', [
        'recipient' => 'kipchoge@example.com',
        'channel' => 'email',
        'notification_type' => 'payment_received',
        'status' => 'sent',
    ]);

    $this->assertDatabaseHas('notification_logs', [
        'recipient' => '+254712999888',
        'channel' => 'sms',
        'notification_type' => 'payment_received',
    ]);
});

test('admin can view, edit, and test-send notification templates', function () {
    Mail::fake();

    $admin = User::factory()->create([
        'email' => 'admin.test@bmas.com',
        'two_factor_secret' => 'SECRETKEY123',
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    // 1. View templates
    $res = $this->actingAs($admin)->getJson('/api/v1/admin/notification-templates');
    $res->assertStatus(200)
        ->assertJsonCount(8, 'data');

    // 2. Edit template
    $updateRes = $this->actingAs($admin)->putJson('/api/v1/admin/notification-templates/registration', [
        'email_subject' => 'Karibu sana to Bariton Music Academy, {{ student_name }}!',
    ]);
    $updateRes->assertStatus(200)
        ->assertJsonPath('template.email_subject', 'Karibu sana to Bariton Music Academy, {{ student_name }}!');

    // 3. Test Send
    $testSendRes = $this->actingAs($admin)->postJson('/api/v1/admin/notification-templates/payment_received/test-send');
    $testSendRes->assertStatus(200)
        ->assertJsonPath('result.status', 'dispatched');
});

test('students can customize optional notification preferences while security and payment remain mandatory', function () {
    $student = User::factory()->create([
        'email' => 'student.prefs@bmas.com',
        'notification_preferences' => [
            'event_reminders' => true,
            'session_reminders' => true,
            'marketing' => false,
        ],
    ]);
    $student->assignRole('student');

    // 1. Get preferences
    $res = $this->actingAs($student)->getJson('/api/v1/student/notification-preferences');
    $res->assertStatus(200)
        ->assertJsonPath('preferences.event_reminders', true)
        ->assertJsonPath('preferences.payment_received', true);

    // 2. Opt out of event reminders
    $updateRes = $this->actingAs($student)->putJson('/api/v1/student/notification-preferences', [
        'event_reminders' => false,
        'payment_received' => false, // Attempt to opt out of mandatory
    ]);
    $updateRes->assertStatus(200);

    $student->refresh();
    expect($student->notification_preferences['event_reminders'])->toBe(false);
    expect($student->notification_preferences['payment_received'])->toBe(true); // Mandatory preserved!

    // Verify service skips opted out event reminder but dispatches payment
    $service = app(NotificationTemplateService::class);
    $eventResult = $service->send('event_reminder', $student, ['event_title' => 'Vocal Bootcamp']);
    expect($eventResult['status'])->toBe('opted_out');

    $paymentResult = $service->send('payment_received', $student, ['amount_formatted' => 'KES 5,000']);
    expect($paymentResult['status'])->toBe('dispatched');
});

test('multi-role dashboards return metrics with date filtering and CSV export streams', function () {
    $admin = User::factory()->create(['two_factor_secret' => 'SECRET123', 'two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $finance = User::factory()->create(['two_factor_secret' => 'SECRET123', 'two_factor_confirmed_at' => now()]);
    $finance->assignRole('finance_manager');

    $instructor = User::factory()->create(['two_factor_secret' => 'SECRET123', 'two_factor_confirmed_at' => now()]);
    $instructor->assignRole('instructor');

    $coordinator = User::factory()->create(['two_factor_secret' => 'SECRET123', 'two_factor_confirmed_at' => now()]);
    $coordinator->assignRole('workshop_coordinator');

    // 1. Admin Dashboard
    $adminRes = $this->actingAs($admin)->getJson('/api/v1/dashboards/admin?from=2026-01-01&to=2026-12-31');
    $adminRes->assertStatus(200)
        ->assertJsonStructure([
            'period',
            'revenue' => ['total_kes', 'total_usd', 'by_method', 'by_item_type'],
            'enrollments' => ['total', 'standard', 'assessment_only'],
            'exams' => ['total_graded', 'passed', 'failed', 'pass_rate_percentage'],
            'events' => ['total_events', 'total_capacity', 'occupied_seats', 'occupancy_percentage'],
            'tutoring' => ['credits_sold', 'sessions_booked', 'attended', 'missed'],
        ]);

    // Admin CSV export
    $adminCsvRes = $this->actingAs($admin)->get('/api/v1/dashboards/admin/export-csv');
    $adminCsvRes->assertStatus(200);
    expect($adminCsvRes->headers->get('Content-Type'))->toContain('text/csv');

    // 2. Finance Dashboard
    $financeRes = $this->actingAs($finance)->getJson('/api/v1/dashboards/finance');
    $financeRes->assertStatus(200)
        ->assertJsonStructure([
            'period',
            'summary' => [
                'total_revenue_kes',
                'cash_entries_count',
                'cash_total_kes',
                'pending_refunds_count',
                'credit_notes_count',
            ],
            'daily_totals',
            'recent_payments',
        ]);

    // Finance CSV export
    $financeCsvRes = $this->actingAs($finance)->get('/api/v1/dashboards/finance/export-csv');
    $financeCsvRes->assertStatus(200);
    expect($financeCsvRes->headers->get('Content-Type'))->toContain('text/csv');

    // 3. Instructor Dashboard
    $instructorRes = $this->actingAs($instructor)->getJson('/api/v1/dashboards/instructor');
    $instructorRes->assertStatus(200)
        ->assertJsonStructure([
            'pending_assignments_count',
            'pending_exams_count',
            'upcoming_sessions_count',
            'courses',
        ]);

    // 4. Coordinator Dashboard
    $coordRes = $this->actingAs($coordinator)->getJson('/api/v1/dashboards/coordinator');
    $coordRes->assertStatus(200)
        ->assertJsonStructure([
            'events_count',
            'total_attendees_checked_in',
            'total_active_bookings',
            'events',
        ]);
});

test('nightly backup command exports database dump and verifies integrity', function () {
    $exitCode = Artisan::call('app:backup', ['--verify' => true]);
    expect($exitCode)->toBe(0);
});

test('endpoints support mobile clients via Bearer token authentication', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Mobile-iPhone-Client')->plainTextToken;

    $res = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me');

    $res->assertStatus(200)
        ->assertJsonPath('data.email', $user->email);
});
