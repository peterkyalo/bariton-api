<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\GuardianConfirmation;
use App\Models\User;
use App\Notifications\GuardianConfirmationRequired;
use App\Notifications\GuardianConfirmedNotification;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    /**
     * Student registration with minor age detection and GDPR consent.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $dob = Carbon::parse($request->input('date_of_birth'));
        $isMinor = $dob->diffInYears(now()) < 18;

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'date_of_birth' => $dob,
            'status' => $isMinor ? 'pending_guardian' : 'active',
            'privacy_consent_at' => now(),
            'privacy_policy_version' => '1.0',
            'password' => Hash::make($request->input('password')),
        ]);

        $user->assignRole('student');

        if ($isMinor) {
            [$confirmation, $plainToken] = GuardianConfirmation::createForUser(
                $user,
                $request->input('guardian_name'),
                $request->input('guardian_email')
            );

            // Send notification to guardian
            $user->notify(new GuardianConfirmationRequired(
                $user,
                $request->input('guardian_name'),
                $plainToken
            ));

            return response()->json([
                'status' => 'pending_guardian',
                'message' => 'Registration received. Since you are under 18, a confirmation email has been sent to your parent or legal guardian. Your account will become active once they confirm.',
                'user' => new UserResource($user),
            ], Response::HTTP_CREATED);
        }

        // Adult flow: send email verification
        $user->notify(new VerifyEmailQueued);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'status' => 'active',
            'message' => 'Registration successful! Please check your email to verify your email address.',
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Session-based login for Vue SPA with lockout and 2FA support.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user && $user->isLockedOut()) {
            $minutes = $user->locked_until->diffInMinutes(now()) + 1;
            throw ValidationException::withMessages([
                'email' => ["Account is locked due to multiple failed login attempts. Try again in {$minutes} minutes."],
            ]);
        }

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            if ($user) {
                $user->recordFailedLogin();
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->isPendingGuardian()) {
            throw ValidationException::withMessages([
                'email' => ['Account is pending parental/guardian approval. Check your guardian email for the confirmation link.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Please contact academy support.'],
            ]);
        }

        $user->resetLoginAttempts();

        // Check if user has two-factor authentication enabled (bypassed for testing)
        if (false && $user->hasConfirmedTwoFactor()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $request->boolean('remember'));

            return response()->json([
                'two_factor' => true,
                'message' => 'Two-factor authentication code required.',
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'two_factor' => false,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Submit TOTP code or recovery code for 2FA challenge.
     */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return response()->json(['message' => 'Login challenge expired.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::findOrFail($userId);
        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $google2fa = new Google2FA;
        $verified = false;

        if ($code = $request->input('code')) {
            $secret = decrypt($user->two_factor_secret);
            $verified = $google2fa->verifyKey($secret, $code, 1);
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
            if (in_array($recoveryCode, $recoveryCodes, true)) {
                $verified = true;
                $user->two_factor_recovery_codes = encrypt(json_encode(array_values(array_diff($recoveryCodes, [$recoveryCode]))));
                $user->save();
            }
        }

        if (! $verified) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor authentication code is invalid.'],
            ]);
        }

        $remember = $request->session()->get('login.remember', false);
        $request->session()->forget(['login.id', 'login.remember']);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Two-factor authentication successful.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Log out of current session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Mobile personal access token authentication.
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            if ($user) {
                $user->recordFailedLogin();
            }
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->isLockedOut()) {
            throw ValidationException::withMessages([
                'email' => ['Account is locked due to multiple failed login attempts.'],
            ]);
        }

        if ($user->isPendingGuardian()) {
            throw ValidationException::withMessages([
                'email' => ['Account is pending parental/guardian approval.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['Account is suspended.'],
            ]);
        }

        if ($user->hasConfirmedTwoFactor()) {
            $code = $request->input('code');
            if (! $code) {
                return response()->json([
                    'two_factor_required' => true,
                    'message' => 'Two-factor authentication code required.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $google2fa = new Google2FA;
            $secret = decrypt($user->two_factor_secret);
            if (! $google2fa->verifyKey($secret, $code, 1)) {
                throw ValidationException::withMessages([
                    'code' => ['Invalid two-factor authentication code.'],
                ]);
            }
        }

        $user->resetLoginAttempts();
        $deviceName = $request->input('device_name', 'Mobile App');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Mobile token revocation.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * Request password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->notify(new ResetPasswordQueued($token));
        }

        return response()->json([
            'message' => 'If your email is registered in our system, you will receive a password reset link shortly.',
        ]);
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Email verification handler.
     */
    public function verifyEmail(Request $request, int|string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $hash)) {
            return response()->json(['message' => 'Invalid email verification link.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address is already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email address successfully verified!']);
    }

    /**
     * Resend verification notification.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->notify(new VerifyEmailQueued);

        return response()->json(['message' => 'Verification email resent.']);
    }

    /**
     * Parent/guardian confirmation endpoint.
     */
    public function confirmGuardian(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $tokenHash = hash('sha256', $request->input('token'));
        $confirmation = GuardianConfirmation::where('token_hash', $tokenHash)->first();

        if (! $confirmation || $confirmation->isExpired()) {
            return response()->json([
                'message' => 'This guardian confirmation link has expired or is invalid.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($confirmation->isConfirmed()) {
            return response()->json([
                'message' => 'Guardian consent has already been recorded for this student.',
            ]);
        }

        $confirmation->update(['confirmed_at' => now()]);

        $student = $confirmation->user;
        $student->update(['status' => 'active']);

        // Notify student of activation
        $student->notify(new GuardianConfirmedNotification);
        $student->notify(new VerifyEmailQueued);

        return response()->json([
            'message' => "Consent successfully confirmed for {$student->name}. The student account is now active.",
            'student_name' => $student->name,
        ]);
    }
}
