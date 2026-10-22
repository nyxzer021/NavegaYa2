<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        }if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }if (app()->environment('production')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' data: https://fonts.bunny.net; script-src 'self' 'unsafe-inline' https://js.culqi.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.clarity.ms https://scripts.clarity.ms https://analytics.tiktok.com; connect-src 'self' https://api.open-meteo.com https://api.culqi.com https://checkout.culqi.com https://www.google-analytics.com https://region1.google-analytics.com https://www.googleadservices.com https://googleads.g.doubleclick.net https://*.clarity.ms https://*.tiktok.com; frame-src https://*.culqi.com https://googleads.g.doubleclick.net");
        }

        return $response;
    }
}
