<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Global Feature-Setup
|--------------------------------------------------------------------------
| Mail::fake verhindert echte Resend-Calls bei Email-Verification etc.
| Storage::fake('r2') hält den R2/S3-Disk lokal — kein Cloud-Upload aus Tests.
*/

pest()->beforeEach(function () {
    Mail::fake();
    Storage::fake('r2');
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
