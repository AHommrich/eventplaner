<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    // WORKAROUND: staging (beta.hommrich.app) is a subdomain of production (hommrich.app).
    // Cookies with domain=hommrich.app are also sent by the browser to beta.hommrich.app,
    // so the prod XSRF-TOKEN overwrites the staging XSRF-TOKEN → 419 on logout.
    // Fix: cookie name configurable per environment via CSRF_COOKIE_NAME in Coolify ENVs.
    // Production: CSRF_COOKIE_NAME=XSRF-TOKEN, Staging: CSRF_COOKIE_NAME=BETA-XSRF-TOKEN
    // Cleaner solution would be: staging on a completely different domain without a shared parent domain.
    protected $cookieName = 'XSRF-TOKEN';

    public function __construct($app, $encrypter)
    {
        parent::__construct($app, $encrypter);

        if ($name = env('CSRF_COOKIE_NAME')) {
            $this->cookieName = $name;
        }
    }
}
