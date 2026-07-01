<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. create drink_catalog_sizes table ───────────────────────────────
        if (! Schema::hasTable('drink_catalog_sizes')) {
            Schema::create('drink_catalog_sizes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('catalog_id');
                $table->double('amount_liter');
                $table->boolean('is_default')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['catalog_id', 'amount_liter'], 'drink_catalog_sizes_catalog_id_amount_liter_unique');
                $table->index('catalog_id', 'drink_catalog_sizes_catalog_id_index');
                $table->foreign('catalog_id')->references('id')->on('drink_catalog')->cascadeOnDelete();
            });
        }

        // The steps below transform *existing* data — on a fresh test DB
        // without drink_catalog rows there is nothing to do.
        if (DB::table('drink_catalog')->count() === 0) {
            $this->normalizeSchema();

            return;
        }

        // ── 2. extract sizes from drink_catalog and store in drink_catalog_sizes ──
        $types = DB::table('drink_catalog')->select('type')->distinct()->pluck('type');

        foreach ($types as $type) {
            $rows = DB::table('drink_catalog')
                ->where('type', $type)
                ->orderBy('amount_liter')
                ->get();

            $canonicalId = $rows->min('id');
            $midIndex = (int) floor($rows->count() / 2);

            foreach ($rows as $index => $row) {
                DB::table('drink_catalog_sizes')->insert([
                    'catalog_id' => $canonicalId,
                    'amount_liter' => $row->amount_liter,
                    'is_default' => ($index === $midIndex) ? 1 : 0,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ── 3. dedupe drinks table: first repoint drink_logs, then delete ──────
        foreach ($types as $type) {
            $ids = DB::table('drink_catalog')
                ->where('type', $type)
                ->orderBy('id')
                ->pluck('id');

            $canonicalId = $ids->first();
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
                        DB::table('drink_logs')
                            ->where('drink_id', $drink->id)
                            ->update(['drink_id' => $canonicalDrink->id]);

                        DB::table('drinks')->where('id', $drink->id)->delete();
                    } else {
                        DB::table('drinks')
                            ->where('id', $drink->id)
                            ->update(['drink_catalog_id' => $canonicalId]);
                    }
                }
            }
        }

        // ── 4. delete non-canonical drink_catalog rows ───────────────────────
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

        if (! empty($toDelete)) {
            DB::table('drink_catalog')->whereIn('id', $toDelete)->delete();
        }

        // ── 5. clean up display_name (strip size suffix) ─────────────────────
        // REGEXP_REPLACE is MySQL-specific — on SQLite (tests) there is no data anyway.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE drink_catalog SET display_name = TRIM(REGEXP_REPLACE(display_name, ' [0-9]+,[0-9]+ l$', ''))");
        }

        $this->normalizeSchema();
    }

    /**
     * Schema finalization — drop `amount_liter` column, unique constraint on `type`.
     */
    private function normalizeSchema(): void
    {
        if (Schema::hasColumn('drink_catalog', 'amount_liter')) {
            Schema::table('drink_catalog', function (Blueprint $table) {
                // MariaDB has a composite index on it — dropIndex by name if present
                try {
                    $table->dropUnique('drink_catalog_type_amount_liter_unique');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('amount_liter');
            });
        }

        $hasUnique = collect(Schema::getIndexes('drink_catalog'))
            ->contains(fn ($i) => ($i['name'] ?? null) === 'drink_catalog_type_unique');

        if (! $hasUnique) {
            try {
                Schema::table('drink_catalog', function (Blueprint $table) {
                    $table->unique('type', 'drink_catalog_type_unique');
                });
            } catch (\Throwable $e) {
                // index already exists in a different form — ignore.
            }
        }
    }

    public function down(): void
    {
        // Not reversible without a backup — this migration is a data structure change.
        // To roll back: restore the database from a backup.
    }
};
