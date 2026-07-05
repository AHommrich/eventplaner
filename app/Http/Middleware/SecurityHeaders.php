<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the standard set of security headers to every response.
 *
 * GDPR Art. 32 requires "appropriate technical measures" — HSTS, frame-deny,
 * sniff-protection, referrer-policy and a minimal CSP are the cheapest items
 * in that toolbox. HSTS is gated on production/staging because long-lived
 * caches make plain HTTP dev unusable otherwise.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app()->environment('production', 'staging')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        // CSP is off in local so the Vite dev server (localhost:5173) can inject
        // its client + HMR websocket. Production/staging load assets from `/build`
        // on the same origin, so `script-src 'self'` is enough there.
        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        // `'unsafe-inline'` for scripts/styles is necessary today because
        // Inertia injects its page payload inline and reka-ui/Tailwind 4
        // produce inline style attributes. Tightening with nonces is a
        // separate hardening task — see docs/gdpr/stage-4-security-headers.md.
        //
        // `worker-src 'self' blob:` — heic2any spawns a Web Worker from a
        // blob URL to convert HEIC → JPEG in the cover upload. Without this,
        // the browser falls back to `script-src` and blocks the worker.
        //
        // `connect-src` allows Nominatim (address autocomplete) and Sentry
        // (`*.ingest.de.sentry.io` — the EU-region ingest endpoint used by
        // both the Laravel and the Vue Sentry clients).
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://nominatim.openstreetmap.org https://*.ingest.de.sentry.io",
            "worker-src 'self' blob:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
