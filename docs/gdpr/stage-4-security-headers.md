# Stage 4 — Security-header middleware

**Effort:** ~30 min
**Outcome:** Every HTTP response leaving the app carries a sensible set of security headers (HSTS, frame-deny, content-type sniffing off, referrer policy, minimal CSP). Verified via `curl -I` against staging.
**Why this matters:** GDPR Art. 32 obliges the controller to implement "appropriate technical measures" to protect personal data. Security headers are the lowest-friction part of that — pure config, no code refactor, and they raise the cost of XSS / clickjacking / mixed-content downgrades dramatically. Plus: the app gets a green grade on `securityheaders.com`, which is a credible signal when potential users look at the demo.

## Headers in scope

| Header | Value | Why |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Tell browsers to never downgrade to HTTP for a year. |
| `X-Frame-Options` | `DENY` | Stops the app from being embedded in an `<iframe>` — kills clickjacking against the photo-game / drink-game submission flows. |
| `X-Content-Type-Options` | `nosniff` | Stops browsers from re-interpreting an uploaded photo as HTML. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Default for new apps; never leaks paths to external sites. |
| `Permissions-Policy` | `camera=(self), microphone=(), geolocation=()` | Only the camera (photo upload) is allowed, and only same-origin. Mic + geo off. |
| `Content-Security-Policy` | see below | Last line of defence against XSS — keep it loose enough that nothing visible breaks, tight enough to matter. |

### CSP starting point

The frontend uses Vite (inline `<script type="module">` tags during dev, hashed assets in prod), `vue-sonner`, Tailwind 4 (inline-style heavy via `style=""`), Cloudflare R2 image URLs, and the OpenStreetMap tile server (for the Nominatim address picker on the event-settings page).

```text
default-src 'self';
script-src 'self' 'unsafe-inline';
style-src 'self' 'unsafe-inline';
img-src 'self' data: blob: https://*.r2.cloudflarestorage.com https://*.tile.openstreetmap.org;
font-src 'self' data:;
connect-src 'self' https://nominatim.openstreetmap.org;
frame-ancestors 'none';
base-uri 'self';
form-action 'self';
```

`'unsafe-inline'` for scripts and styles is unfortunate but matches the existing codebase (Inertia injects a JSON blob inline, Tailwind generates inline styles via reka-ui). Tightening later via nonces is a follow-up — Stage 4 ships the realistic-today version.

## Steps

### 1. Middleware

`app/Http/Middleware/SecurityHeaders.php` — new:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->csp());

        return $response;
    }

    private function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://*.r2.cloudflarestorage.com https://*.tile.openstreetmap.org",
            "font-src 'self' data:",
            "connect-src 'self' https://nominatim.openstreetmap.org",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
```

### 2. Register globally

`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

Append, not prepend — keeps headers reaching all responses including ones from earlier middleware short-circuits (auth redirects etc.).

### 3. Local-dev escape hatch

HSTS in local dev is annoying — once a browser caches it, you cannot use plain HTTP for that hostname for a year. Add an `APP_ENV`-guard:

```php
if (app()->environment('production', 'staging')) {
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
}
```

The rest of the headers fire in every environment — they don't cache long-term, so they're safe everywhere.

### 4. CSP smoke test

Before pushing, click through the app once with the browser dev-tools "Console" tab open:

- Welcome page — should be quiet
- Login + register
- Dashboard — sidebar + active event switcher
- Event settings — palette pickers, font preview, Leaflet map (this is the one most likely to trip CSP)
- Photo upload — confirm the R2 URL renders, the camera button works
- Projector page in a fresh tab — autoplay slideshow

If anything CSP-blocks, add the source to the appropriate directive rather than dropping the policy.

### 5. Tests

`tests/Feature/SecurityHeadersTest.php` — new, 3 cases:

```php
it('sends HSTS in production-like envs', function () {
    config(['app.env' => 'production']);
    $this->get('/')->assertHeader('Strict-Transport-Security');
});

it('always sends frame-deny + nosniff + referrer-policy', function () {
    $response = $this->get('/');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('sets a CSP that allows R2 images and OSM tiles', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');
    expect($csp)->toContain('r2.cloudflarestorage.com');
    expect($csp)->toContain('tile.openstreetmap.org');
});
```

### 6. Verify after deploy

```bash
curl -sI https://beta.hommrich.app | grep -iE 'strict-transport|frame-options|content-type|referrer-policy|permissions-policy|content-security'
```

All six headers must appear. Then check `https://securityheaders.com/?q=beta.hommrich.app` — should score B+ or A.

## File list

| File | Action |
|---|---|
| `app/Http/Middleware/SecurityHeaders.php` | new |
| `bootstrap/app.php` | register middleware |
| `tests/Feature/SecurityHeadersTest.php` | new |

## Acceptance criteria

- [ ] `curl -I` against staging shows all six headers
- [ ] HSTS is absent in local + testing envs (so local dev can still use HTTP)
- [ ] All three Pest tests pass
- [ ] No visual regression — manual click-through of welcome, dashboard, event-settings, photo-upload, projector
- [ ] `securityheaders.com` returns B+ or better

## Commit suggestion

```
feat(security): add HSTS + CSP + frame-deny middleware
```
