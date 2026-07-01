<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety guard: tests must NEVER run against the dev DB.
     *
     * RefreshDatabase would run `migrate:fresh` on the active connection
     * and wipe the real dev DB — this happened exactly once and must
     * NEVER happen again.
     *
     * Allowed are:
     *   - `laravel_test`        (MariaDB test DB in the same container)
     *   - `:memory:`            (SQLite in-memory, if ever desired)
     *   - database name ending in `_test` / `_testing`
     */
    private const ALLOWED_DB_NAMES = ['laravel_test', ':memory:'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTestDatabase();
    }

    private function assertTestDatabase(): void
    {
        $db = DB::connection()->getDatabaseName();

        $allowed = in_array($db, self::ALLOWED_DB_NAMES, true)
            || str_ends_with($db, '_test')
            || str_ends_with($db, '_testing');

        if (! $allowed) {
            throw new \RuntimeException(
                "🚨 Test versucht gegen DB '{$db}' zu laufen. Nur Test-DBs sind erlaubt ".
                '(laravel_test, :memory:, *_test, *_testing). '.
                'Prüfe phpunit.xml DB_CONNECTION + config/database.php.'
            );
        }
    }
}
