<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. size_id (nullable) zu drinks hinzufügen ───────────────────────
        if (!Schema::hasColumn('drinks', 'size_id')) {
            DB::statement('ALTER TABLE drinks ADD COLUMN size_id bigint(20) unsigned NULL AFTER drink_catalog_id');
        }

        // ── 2. Bestehende Zeilen expandieren (1 pro Typ → N pro Größe) ───────
        // Nur wenn noch NULL-Zeilen existieren (idempotent)
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

        // ── 3. drink_logs umhängen ────────────────────────────────────────────
        DB::statement("
            UPDATE drink_logs dl
            JOIN drinks d_old ON d_old.id = dl.drink_id
            JOIN drinks d_new ON d_new.event_id = d_old.event_id
                              AND d_new.drink_catalog_id = d_old.drink_catalog_id
                              AND d_new.size_id = dl.size_id
            SET dl.drink_id = d_new.id
            WHERE dl.size_id IS NOT NULL
        ");

        // ── 4. FK droppen falls vorhanden, size_id NOT NULL, FK neu mit CASCADE ─
        try {
            DB::statement('ALTER TABLE drinks DROP FOREIGN KEY drinks_size_id_foreign');
        } catch (\Exception $e) {
            // noch kein FK vorhanden — ignorieren
        }
        DB::statement('ALTER TABLE drinks MODIFY COLUMN size_id bigint(20) unsigned NOT NULL');
        DB::statement('ALTER TABLE drinks ADD CONSTRAINT drinks_size_id_foreign FOREIGN KEY (size_id) REFERENCES drink_catalog_sizes(id) ON DELETE CASCADE');

        // ── 5. Unique-Constraint tauschen ─────────────────────────────────────
        try {
            DB::statement('ALTER TABLE drinks DROP INDEX drinks_event_id_drink_catalog_id_unique');
        } catch (\Exception $e) {
            // kein alter Constraint — ignorieren
        }
        try {
            DB::statement('ALTER TABLE drinks DROP INDEX drinks_event_id_size_id_unique');
        } catch (\Exception $e) {
            // noch nicht vorhanden — ignorieren
        }
        DB::statement('ALTER TABLE drinks ADD UNIQUE KEY drinks_event_id_size_id_unique (event_id, size_id)');
    }

    public function down(): void
    {
        // Nicht reversibel ohne Backup
    }
};
