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
        // OpenStreetMap exige un Referer pour ses tuiles : on envoie l'origine, jamais l'URL complète.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(self)');

        // Pas de CSP quand le serveur de développement Vite tourne (rechargement à chaud).
        if (! file_exists(public_path('hot'))) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https://tile.openstreetmap.org",
                "media-src 'self' blob:",
                "connect-src 'self' https://nominatim.openstreetmap.org https://routing.openstreetmap.de",
                "worker-src 'self'",
                "manifest-src 'self'",
                "object-src 'none'",
                "base-uri 'none'",
                "frame-ancestors 'none'",
                "form-action 'self'",
            ]));
        }

        return $response;
    }
}
