<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety-Guard: Tests dürfen NIE gegen die Dev-DB laufen.
     *
     * RefreshDatabase würde `migrate:fresh` auf der aktiven Connection ausführen
     * und damit die echte Dev-DB leeren — das ist genau einmal passiert und darf
     * NIE wieder vorkommen.
     *
     * Erlaubt sind:
     *   - `laravel_test`        (MariaDB-Test-DB im selben Container)
     *   - `:memory:`            (SQLite-In-Memory falls je gewünscht)
     *   - Datenbankname endet auf `_test` / `_testing`
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

        if (!$allowed) {
            throw new \RuntimeException(
                "🚨 Test versucht gegen DB '{$db}' zu laufen. Nur Test-DBs sind erlaubt ".
                "(laravel_test, :memory:, *_test, *_testing). ".
                "Prüfe phpunit.xml DB_CONNECTION + config/database.php."
            );
        }
    }
}
