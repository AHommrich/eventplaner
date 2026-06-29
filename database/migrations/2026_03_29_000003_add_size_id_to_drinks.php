<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. size_id (nullable) hinzufügen ──────────────────────────────────
        if (!Schema::hasColumn('drinks', 'size_id')) {
            Schema::table('drinks', function (Blueprint $table) {
                $table->unsignedBigInteger('size_id')->nullable()->after('drink_catalog_id');
            });
        }

        $isMysql = DB::connection()->getDriverName() === 'mysql';

        // ── 2. Bestehende Zeilen expandieren — nur wenn Daten vorhanden ──────
        $nullCount = DB::table('drinks')->whereNull('size_id')->count();
        if ($nullCount > 0) {
            $drinks = DB::table('drinks')->whereNull('size_id')->get();

            foreach ($drinks as $drink) {
                $sizes = DB::table('drink_catalog_sizes')
                    ->where('catalog_id', $drink->drink_catalog_id)
                    ->orderBy('sort_order')
                    ->get();

                if ($sizes->isEmpty()) {
                    continue;
                }

                $firstSize = $sizes->shift();
                DB::table('drinks')->where('id', $drink->id)->update(['size_id' => $firstSize->id]);

                foreach ($sizes as $size) {
                    $exists = DB::table('drinks')
                        ->where('event_id', $drink->event_id)
                        ->where('size_id', $size->id)
                        ->exists();
                    if (!$exists) {
                        DB::table('drinks')->insert([
                            'event_id'         => $drink->event_id,
                            'drink_catalog_id' => $drink->drink_catalog_id,
                            'size_id'          => $size->id,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }
        }

        // ── 3. drink_logs umhängen — UPDATE JOIN ist MySQL-spezifisch ─────────
        if ($isMysql) {
            DB::statement("
                UPDATE drink_logs dl
                JOIN drinks d_old ON d_old.id = dl.drink_id
                JOIN drinks d_new ON d_new.event_id = d_old.event_id
                                  AND d_new.drink_catalog_id = d_old.drink_catalog_id
                                  AND d_new.size_id = dl.size_id
                SET dl.drink_id = d_new.id
                WHERE dl.size_id IS NOT NULL
            ");
        }

        // ── 4. size_id auf NOT NULL hochziehen + Foreign Key ──────────────────
        // FK + Indizes via DB::statement, damit try/catch greift (Schema::table batched
        // Statements und schluckt einzelne Failures innerhalb des Closures nicht).
        if ($isMysql) {
            try { DB::statement('ALTER TABLE drinks DROP FOREIGN KEY drinks_size_id_foreign'); } catch (\Throwable $e) {}
            DB::statement('ALTER TABLE drinks MODIFY COLUMN size_id bigint(20) unsigned NOT NULL');
            DB::statement('ALTER TABLE drinks ADD CONSTRAINT drinks_size_id_foreign FOREIGN KEY (size_id) REFERENCES drink_catalog_sizes(id) ON DELETE CASCADE');
        } else {
            // SQLite kann FK/NOT-NULL nicht nachträglich setzen; size_id bleibt nullable im Test.
            Schema::table('drinks', function (Blueprint $table) {
                $table->foreign('size_id')->references('id')->on('drink_catalog_sizes')->cascadeOnDelete();
            });
        }

        // ── 5. Unique-Constraint tauschen ─────────────────────────────────────
        if ($isMysql) {
            try { DB::statement('ALTER TABLE drinks DROP INDEX drinks_event_id_drink_catalog_id_unique'); } catch (\Throwable $e) {}
            try { DB::statement('ALTER TABLE drinks DROP INDEX drinks_event_id_size_id_unique'); } catch (\Throwable $e) {}
            DB::statement('ALTER TABLE drinks ADD UNIQUE KEY drinks_event_id_size_id_unique (event_id, size_id)');
        } else {
            Schema::table('drinks', function (Blueprint $table) {
                $table->unique(['event_id', 'size_id'], 'drinks_event_id_size_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Nicht reversibel ohne Backup
    }
};
