<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnabled
{
    /**
     * Paths exempt from 2FA enforcement to allow the user to set up 2FA or log out.
     */
    protected array $exemptRoutes = [
        'api/v1/me',
        'api/v1/auth/logout',
        'api/v1/me/two-factor-qr-code',
        'api/v1/me/two-factor-secret-key',
        'api/v1/me/two-factor-authentication',
        'api/v1/me/confirmed-two-factor-authentication',
        'api/v1/auth/user/two-factor-qr-code',
        'api/v1/auth/user/two-factor-secret-key',
        'api/v1/auth/user/two-factor-authentication',
        'api/v1/auth/user/confirmed-two-factor-authentication',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        foreach ($this->exemptRoutes as $exempt) {
            if ($request->is($exempt)) {
                return $next($request);
            }
        }

        if ($user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor()) {
            return response()->json([
                'message' => 'Two-factor authentication (TOTP) setup is strictly mandatory for your role. Please enable two-factor authentication in your account settings.',
                'code' => 'two_factor_setup_required',
            ], Response::HTTP_LOCKED);
        }

        return $next($request);
    }
}
