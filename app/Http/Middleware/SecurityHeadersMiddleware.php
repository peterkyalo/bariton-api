<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/v1/media/*')) {
            $allowedAncestors = implode(' ', array_unique(array_filter([
                "'self'",
                env('FRONTEND_URL', 'http://localhost:5173'),
                'http://localhost:5173',
                'http://127.0.0.1:5173',
                'https://app.bmas.com',
            ])));
            $response->headers->set('Content-Security-Policy', "frame-ancestors {$allowedAncestors}");
            $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
            $response->headers->remove('X-Frame-Options');

            $origin = $request->headers->get('Origin');
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Vary', 'Origin');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Force HSTS in production or staging
        if (config('app.env') === 'production' || $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
