<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. drink_catalog_sizes Tabelle anlegen ────────────────────────────
        DB::statement("
            CREATE TABLE drink_catalog_sizes (
                id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                catalog_id   bigint(20) unsigned NOT NULL,
                amount_liter double NOT NULL,
                is_default   tinyint(1) NOT NULL DEFAULT 0,
                sort_order   int(11) NOT NULL DEFAULT 0,
                created_at   timestamp NULL DEFAULT NULL,
                updated_at   timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY drink_catalog_sizes_catalog_id_amount_liter_unique (catalog_id, amount_liter),
                KEY drink_catalog_sizes_catalog_id_index (catalog_id),
                CONSTRAINT drink_catalog_sizes_catalog_id_foreign
                    FOREIGN KEY (catalog_id) REFERENCES drink_catalog (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── 2. Größen aus drink_catalog extrahieren und in drink_catalog_sizes speichern ──
        $types = DB::table('drink_catalog')->select('type')->distinct()->pluck('type');

        foreach ($types as $type) {
            $rows = DB::table('drink_catalog')
                ->where('type', $type)
                ->orderBy('amount_liter')
                ->get();

            $canonicalId = $rows->min('id');
            $midIndex    = (int) floor($rows->count() / 2);

            foreach ($rows as $index => $row) {
                DB::table('drink_catalog_sizes')->insert([
                    'catalog_id'   => $canonicalId,
                    'amount_liter' => $row->amount_liter,
                    'is_default'   => ($index === $midIndex) ? 1 : 0,
                    'sort_order'   => $index,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        // ── 3. drinks-Tabelle dedup: zuerst drink_logs umhängen, dann löschen ──
        $types = DB::table('drink_catalog')->select('type')->distinct()->pluck('type');

        foreach ($types as $type) {
            $ids = DB::table('drink_catalog')
                ->where('type', $type)
                ->orderBy('id')
                ->pluck('id');

            $canonicalId  = $ids->first();
            $duplicateIds = $ids->slice(1)->values();

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            foreach ($duplicateIds as $dupCatalogId) {
                $drinkRows = DB::table('drinks')->where('drink_catalog_id', $dupCatalogId)->get();

                foreach ($drinkRows as $drink) {
                    $canonicalDrink = DB::table('drinks')
                        ->where('event_id', $drink->event_id)
                        ->where('drink_catalog_id', $canonicalId)
                        ->first();

                    if ($canonicalDrink) {
                        // Event hat schon den kanonischen Drink → Logs umhängen, dann Duplikat löschen
                        DB::table('drink_logs')
                            ->where('drink_id', $drink->id)
                            ->update(['drink_id' => $canonicalDrink->id]);

                        DB::table('drinks')->where('id', $drink->id)->delete();
                    } else {
                        // Nur catalog_id auf kanonisch umstellen
                        DB::table('drinks')
                            ->where('id', $drink->id)
                            ->update(['drink_catalog_id' => $canonicalId]);
                    }
                }
            }
        }

        // ── 4. Nicht-kanonische drink_catalog-Zeilen löschen ─────────────────
        $types = DB::table('drink_catalog')->select('type')->distinct()->pluck('type');
        $toDelete = [];

        foreach ($types as $type) {
            $ids = DB::table('drink_catalog')
                ->where('type', $type)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1) as $id) {
                $toDelete[] = $id;
            }
        }

        if (!empty($toDelete)) {
            DB::table('drink_catalog')->whereIn('id', $toDelete)->delete();
        }

        // ── 5. display_name bereinigen (Größen-Suffix entfernen) ─────────────
        DB::statement("UPDATE drink_catalog SET display_name = TRIM(REGEXP_REPLACE(display_name, ' [0-9]+,[0-9]+ l$', ''))");

        // ── 6. amount_liter aus drink_catalog entfernen ───────────────────────
        DB::statement('ALTER TABLE drink_catalog DROP INDEX drink_catalog_type_amount_liter_unique');
        DB::statement('ALTER TABLE drink_catalog DROP COLUMN amount_liter');
        DB::statement('ALTER TABLE drink_catalog ADD UNIQUE KEY drink_catalog_type_unique (type)');
    }

    public function down(): void
    {
        // Nicht reversibel ohne Backup — diese Migration ist eine Daten-Strukturänderung.
        // Zum Rollback: Datenbank aus Backup wiederherstellen.
    }
};
