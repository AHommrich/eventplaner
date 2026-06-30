<?php

use Illuminate\Support\Facades\DB;

/**
 * Safety net: ensures tests run against the isolated test DB.
 * Mandatory — otherwise RefreshDatabase would wipe the dev DB.
 */
it('tests run against the laravel_test database, not laravel', function () {
    $name = DB::connection()->getDatabaseName();
    expect($name)->toBe('laravel_test', "Tests dürfen NUR gegen laravel_test laufen, nicht gegen '{$name}'.");
});
