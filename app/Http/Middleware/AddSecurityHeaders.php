<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // geolocation=(self): the Live Map / Share My Location feature needs
        // location access. Voice/video calling uses same-origin WebRTC, so the
        // browser must also be allowed to ask for camera/microphone permission.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self)');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
