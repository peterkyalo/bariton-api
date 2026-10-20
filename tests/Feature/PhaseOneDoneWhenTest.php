<?php

use App\Models\GuardianConfirmation;
use App\Models\NotificationLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

test('a student registers, verifies email and logs in on app.bmas.com', function () {
    Notification::fake();

    // 1. Adult student registration
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Wanjiru Mwangi',
        'email' => 'wanjiru@example.com',
        'phone' => '+254712345678',
        'date_of_birth' => '2000-01-15',
        'privacy_consent' => true,
        'password' => 'Password123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'active');

    $student = User::where('email', 'wanjiru@example.com')->first();
    expect($student)->not->toBeNull();
    expect($student->hasRole('student'))->toBeTrue();
    expect($student->hasVerifiedEmail())->toBeFalse();

    // 2. Email verification
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $student->id, 'hash' => sha1($student->email)]
    );

    $parsed = parse_url($verificationUrl);
    $verifyResponse = $this->getJson($parsed['path'].'?'.$parsed['query']);
    $verifyResponse->assertOk();

    $student->refresh();
    expect($student->hasVerifiedEmail())->toBeTrue();

    // 3. Login
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'wanjiru@example.com',
        'password' => 'Password123!',
    ]);

    $loginResponse->assertOk()
        ->assertJsonPath('two_factor', false)
        ->assertJsonPath('data.email', 'wanjiru@example.com');

    // 4. Check /me authenticated profile
    $meResponse = $this->getJson('/api/v1/me');
    $meResponse->assertOk()
        ->assertJsonPath('data.email', 'wanjiru@example.com');
});

test('a student under 18 stays inactive until the guardian confirms', function () {
    Notification::fake();

    // Minor (born 15 years ago) registers
    $minorDob = now()->subYears(15)->format('Y-m-d');

    $regResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Junior Omondi',
        'email' => 'junior@example.com',
        'phone' => '+254722112233',
        'date_of_birth' => $minorDob,
        'privacy_consent' => true,
        'guardian_name' => 'Mary Omondi',
        'guardian_email' => 'mary.omondi@example.com',
        'password' => 'Password123!',
    ]);

    $regResponse->assertCreated()
        ->assertJsonPath('status', 'pending_guardian');

    $student = User::where('email', 'junior@example.com')->first();
    expect($student->status)->toBe('pending_guardian');
    expect($student->isMinor())->toBeTrue();

    // Attempting login while pending guardian should fail
    $loginAttempt = $this->postJson('/api/v1/auth/login', [
        'email' => 'junior@example.com',
        'password' => 'Password123!',
    ]);

    $loginAttempt->assertStatus(422);

    // Retrieve guardian confirmation token
    $confirmation = GuardianConfirmation::where('user_id', $student->id)->first();
    expect($confirmation)->not->toBeNull();
    expect($confirmation->confirmed_at)->toBeNull();

    // Simulate guardian clicking confirmation link with valid token
    $plainToken = 'test-guardian-token-12345';
    $confirmation->update(['token_hash' => hash('sha256', $plainToken)]);

    $confirmResponse = $this->postJson('/api/v1/guardian/confirm', [
        'token' => $plainToken,
    ]);

    $confirmResponse->assertOk()
        ->assertJsonPath('student_name', 'Junior Omondi');

    $student->refresh();
    expect($student->status)->toBe('active');

    // Now minor student can log in
    $loginSuccess = $this->postJson('/api/v1/auth/login', [
        'email' => 'junior@example.com',
        'password' => 'Password123!',
    ]);

    $loginSuccess->assertOk()
        ->assertJsonPath('data.status', 'active');
});

test('an admin creates a finance_staff user who must set up two-factor authentication', function () {
    $admin = User::create([
        'name' => 'System Administrator',
        'email' => 'admin.test@bmas.com',
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('ADMINSECRET123'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    // Admin creates finance_staff user
    $createResponse = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Alice Finance',
        'email' => 'alice@bmas.com',
        'phone' => '+254700998877',
        'roles' => ['finance_staff'],
        'password' => 'SecurePass123!',
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('data.email', 'alice@bmas.com')
        ->assertJsonPath('data.two_factor_required', true)
        ->assertJsonPath('data.two_factor_enabled', false);

    $financeStaff = User::where('email', 'alice@bmas.com')->first();
    expect($financeStaff)->not->toBeNull();
    expect($financeStaff->requiresTwoFactor())->toBeTrue();
    expect($financeStaff->hasConfirmedTwoFactor())->toBeFalse();

    // Logout admin
    $this->postJson('/api/v1/auth/logout');

    $staffLogin = $this->postJson('/api/v1/auth/login', [
        'email' => 'alice@bmas.com',
        'password' => 'SecurePass123!',
    ]);

    $staffLogin->assertOk();

    // Trying to access an admin/staff route is blocked with 423 two_factor_setup_required
    $blockedResponse = $this->actingAs($financeStaff)->getJson('/api/v1/admin/settings');
    $blockedResponse->assertStatus(423)
        ->assertJsonPath('code', 'two_factor_setup_required');

    // Finance staff completes 2FA setup
    $enableResponse = $this->actingAs($financeStaff)->postJson('/api/v1/me/two-factor-authentication');
    $enableResponse->assertOk();

    $financeStaff->refresh();
    expect($financeStaff->two_factor_secret)->not->toBeNull();

    // Generate valid TOTP code
    $google2fa = new Google2FA;
    $secret = decrypt($financeStaff->two_factor_secret);
    $code = $google2fa->getCurrentOtp($secret);

    $confirmResponse = $this->actingAs($financeStaff)->postJson('/api/v1/me/confirmed-two-factor-authentication', [
        'code' => $code,
    ]);

    $confirmResponse->assertOk()
        ->assertJsonPath('data.two_factor_enabled', true);

    $financeStaff->refresh();
    expect($financeStaff->hasConfirmedTwoFactor())->toBeTrue();
});

test('each role sees only its own menu and permissions', function () {
    // Student
    $student = User::create([
        'name' => 'Test Student',
        'email' => 'std@example.com',
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $student->assignRole('student');

    $studentProfile = $this->actingAs($student)->getJson('/api/v1/me');
    $studentPerms = $studentProfile->json('data.permissions');
    expect($studentPerms)->toContain('dashboard.student')
        ->and($studentPerms)->not->toContain('users.view')
        ->and($studentPerms)->not->toContain('settings.view');

    // Student is unauthorized to view admin users
    $forbidden = $this->actingAs($student)->getJson('/api/v1/admin/users');
    $forbidden->assertForbidden();

    // Instructor
    $instructor = User::create([
        'name' => 'Test Instructor',
        'email' => 'inst@example.com',
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $instructor->assignRole('instructor');

    $instructorProfile = $this->actingAs($instructor)->getJson('/api/v1/me');
    expect($instructorProfile->json('data.permissions'))->toContain('dashboard.instructor')
        ->and($instructorProfile->json('data.permissions'))->not->toContain('users.view')
        ->and($instructorProfile->json('data.permissions'))->not->toContain('settings.view');

    // Finance staff
    $finance = User::create([
        'name' => 'Test Finance',
        'email' => 'fin@example.com',
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('FINSECRET123'),
        'two_factor_confirmed_at' => now(),
    ]);
    $finance->assignRole('finance_staff');

    $financeProfile = $this->actingAs($finance)->getJson('/api/v1/me');
    expect($financeProfile->json('data.permissions'))->toEqualCanonicalizing(['dashboard.finance']);
});

test('a test SMS logs a delivery status', function () {
    $admin = User::create([
        'name' => 'SMS Admin',
        'email' => 'smsadmin@bmas.com',
        'password' => Hash::make('Password123!'),
        'status' => 'active',
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('SMSADMINSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);
    $admin->assignRole('admin');

    $smsResponse = $this->actingAs($admin)->postJson('/api/v1/admin/test-sms', [
        'phone' => '0712345678',
        'message' => 'Bariton Academy: Testing Africa\'s Talking SMS delivery pipeline.',
    ]);

    $smsResponse->assertCreated()
        ->assertJsonPath('data.channel', 'sms')
        ->assertJsonPath('data.recipient', '+254712345678')
        ->assertJsonPath('data.status', 'delivered');

    $log = NotificationLog::where('channel', 'sms')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->recipient)->toBe('+254712345678');
    expect($log->status)->toBe('delivered');
});

test('account lockout enforces 15 minute lock after 5 failed logins', function () {
    $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);

    $user = User::create([
        'name' => 'Lockout User',
        'email' => 'lockout@example.com',
        'password' => Hash::make('CorrectPassword123!'),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    // 5 failed login attempts
    for ($i = 1; $i <= 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'WrongPassword',
        ]);
    }

    $user->refresh();
    expect($user->failed_login_count)->toBe(5);
    expect($user->isLockedOut())->toBeTrue();

    // 6th attempt with correct password fails because account is locked
    $attempt = $this->postJson('/api/v1/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $attempt->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('mobile personal access token login and logout works', function () {
    $user = User::create([
        'name' => 'Mobile Student',
        'email' => 'mobile@example.com',
        'password' => Hash::make('MobileSecret123!'),
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
    $user->assignRole('student');

    $tokenResponse = $this->postJson('/api/v1/auth/token', [
        'email' => 'mobile@example.com',
        'password' => 'MobileSecret123!',
        'device_name' => 'Pixel 9 Pro',
    ]);

    $tokenResponse->assertOk()
        ->assertJsonStructure(['token', 'data']);

    $plainToken = $tokenResponse->json('token');

    // Access protected endpoint with bearer token
    $meResponse = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson('/api/v1/me');

    $meResponse->assertOk()
        ->assertJsonPath('data.email', 'mobile@example.com');

    // Revoke token
    $logoutResponse = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->deleteJson('/api/v1/auth/token');

    $logoutResponse->assertOk();

    // Clear session & memory guard state to test fresh bearer token validation
    $this->app['auth']->forgetGuards();

    $meAfter = $this->withHeader('Authorization', "Bearer {$plainToken}")
        ->getJson('/api/v1/me');

    $meAfter->assertUnauthorized();
});
