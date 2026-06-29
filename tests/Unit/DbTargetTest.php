<?php

use Illuminate\Support\Facades\DB;

/**
 * Safety-Net: stellt sicher, dass Tests gegen die isolierte Test-DB laufen.
 * Wird Pflicht — sonst würde RefreshDatabase die Dev-DB wipen.
 */
it('tests run against the laravel_test database, not laravel', function () {
    $name = DB::connection()->getDatabaseName();
    expect($name)->toBe('laravel_test', "Tests dürfen NUR gegen laravel_test laufen, nicht gegen '{$name}'.");
});
