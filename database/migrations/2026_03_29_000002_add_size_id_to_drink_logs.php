<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // size_id + amount_liter zu drink_logs hinzufügen
        DB::statement('ALTER TABLE drink_logs ADD COLUMN size_id bigint(20) unsigned NULL AFTER drink_id');
        DB::statement('ALTER TABLE drink_logs ADD COLUMN amount_liter double NULL AFTER size_id');
        DB::statement('ALTER TABLE drink_logs ADD KEY drink_logs_size_id_index (size_id)');
        DB::statement('ALTER TABLE drink_logs ADD CONSTRAINT drink_logs_size_id_foreign FOREIGN KEY (size_id) REFERENCES drink_catalog_sizes (id) ON DELETE SET NULL');

        // Backfill: Default-Größe des Typs für bestehende Logs setzen
        DB::statement("
            UPDATE drink_logs dl
            JOIN drinks d ON d.id = dl.drink_id
            JOIN drink_catalog_sizes dcs ON dcs.catalog_id = d.drink_catalog_id AND dcs.is_default = 1
            SET dl.size_id = dcs.id, dl.amount_liter = dcs.amount_liter
            WHERE dl.size_id IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drink_logs DROP FOREIGN KEY drink_logs_size_id_foreign');
        DB::statement('ALTER TABLE drink_logs DROP KEY drink_logs_size_id_index');
        DB::statement('ALTER TABLE drink_logs DROP COLUMN size_id');
        DB::statement('ALTER TABLE drink_logs DROP COLUMN amount_liter');
    }
};
