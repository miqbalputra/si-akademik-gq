<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.force_https') && ! $request->secure()) {
            $appUrl = (string) config('app.url');
            $canonicalHost = parse_url($appUrl, PHP_URL_HOST) ?: $request->getHost();
            $uri = $request->getRequestUri();

            return redirect()->to('https://'.$canonicalHost.$uri, 301);
        }

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self' https://accounts.google.com; ".
            "img-src 'self' data: blob: https:; font-src 'self' data: https://fonts.bunny.net; ".
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
            "connect-src 'self' https: wss:; frame-src 'self' https://accounts.google.com"
        );
        $headers->remove('X-Powered-By');

        if (config('app.env') === 'production' && $request->secure()) {
            $headers->set('Strict-Transport-Security', 'max-age='.config('security.hsts_max_age').'; includeSubDomains');
        }

        return $response;
    }
}
