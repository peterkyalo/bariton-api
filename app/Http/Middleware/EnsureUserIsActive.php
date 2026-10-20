<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isLockedOut()) {
            $minutes = $user->locked_until->diffInMinutes(now()) + 1;

            return response()->json([
                'message' => "Account is temporarily locked due to failed login attempts. Try again in {$minutes} minutes.",
                'code' => 'account_locked',
            ], Response::HTTP_LOCKED);
        }

        if ($user->isPendingGuardian()) {
            return response()->json([
                'message' => 'Account is pending parent/guardian approval. Check guardian email for confirmation link.',
                'code' => 'pending_guardian_approval',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->isSuspended()) {
            return response()->json([
                'message' => 'Your account has been suspended. Please contact academy administration.',
                'code' => 'account_suspended',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
