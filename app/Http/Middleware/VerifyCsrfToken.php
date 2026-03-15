<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $cookieName = 'XSRF-TOKEN';

    public function __construct($app, $encrypter)
    {
        parent::__construct($app, $encrypter);

        if ($name = env('CSRF_COOKIE_NAME')) {
            $this->cookieName = $name;
        }
    }
}
