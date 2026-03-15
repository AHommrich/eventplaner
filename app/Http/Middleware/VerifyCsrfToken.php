<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    // WORKAROUND: staging (beta.hommrich.app) ist eine Subdomain von production (hommrich.app).
    // Cookies mit domain=hommrich.app werden vom Browser auch an beta.hommrich.app geschickt,
    // wodurch der XSRF-TOKEN von prod den XSRF-TOKEN von staging überschreibt → 419 auf logout.
    // Fix: Cookie-Name pro Environment konfigurierbar via CSRF_COOKIE_NAME in Coolify ENVs.
    // Production: CSRF_COOKIE_NAME=XSRF-TOKEN, Staging: CSRF_COOKIE_NAME=BETA-XSRF-TOKEN
    // Saubere Lösung wäre: Staging auf einer komplett anderen Domain ohne gemeinsame Parent-Domain.
    protected $cookieName = 'XSRF-TOKEN';

    public function __construct($app, $encrypter)
    {
        parent::__construct($app, $encrypter);

        if ($name = env('CSRF_COOKIE_NAME')) {
            $this->cookieName = $name;
        }
    }
}
