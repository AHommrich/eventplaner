<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make `food_specials` a per-event delta catalog (mirrors PhotoGameTaskCatalog).
 *
 * `event_id` is NULLABLE:
 *   - null            = global, read-only seeded template (auto-visible to every
 *                       event; only superadmin edits, manager controllers never
 *                       write null).
 *   - <event id>      = event-local custom entry.
 * Reads = templates ∪ event-local. Writes always set event_id = activeEvent().
 *
 * Seeded templates are identified by a non-null `translation_key` (the seed
 * migration sets it; FoodSpecialController::store never does). Custom rows
 * (translation_key IS NULL) are made event-private via the guest_food_special
 * pivot; a custom row shared across events is cloned per event and its pivot
 * references repointed to the local copy.
 *
 * Staged, NOT one transaction: MariaDB implicitly commits on ALTER TABLE, so
 * the column add, the backfill and the FK are separate steps with a fail-loud
 * validation query before the FK is added. See docs/EVENT_MANAGER_ROLE_PLAN.md
 * §11 P0.1 and §13.5 #1 (FK is cascadeOnDelete, NOT nullOnDelete — nulling a
 * private local row on event deletion would leak it as a global template).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the nullable column (no FK yet).
        if (! Schema::hasColumn('food_specials', 'event_id')) {
            Schema::table('food_specials', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->after('id');
            });
        }

        // 2. Backfill custom rows (idempotent: only touches translation_key IS
        //    NULL rows still lacking an event_id; templates stay null).
        $defaultEventId = DB::table('events')->orderBy('id')->value('id');

        $customRows = DB::table('food_specials')
            ->whereNull('event_id')
            ->whereNull('translation_key')
            ->pluck('id');

        foreach ($customRows as $foodSpecialId) {
            $eventIds = DB::table('guest_food_special as gfs')
                ->join('guests as g', 'g.id', '=', 'gfs.guest_id')
                ->where('gfs.food_special_id', $foodSpecialId)
                ->whereNotNull('g.event_id')
                ->distinct()
                ->pluck('g.event_id')
                ->all();

            if (empty($eventIds)) {
                // Orphan custom row referenced by no (event-bound) guest — assign
                // to the default event so it never leaks as a global template.
                if ($defaultEventId !== null) {
                    DB::table('food_specials')->where('id', $foodSpecialId)->update(['event_id' => $defaultEventId]);
                }

                continue;
            }

            // Keep the original for the first event; clone-and-repoint the rest.
            $primaryEventId = array_shift($eventIds);
            DB::table('food_specials')->where('id', $foodSpecialId)->update(['event_id' => $primaryEventId]);

            $original = DB::table('food_specials')->where('id', $foodSpecialId)->first();

            foreach ($eventIds as $eventId) {
                $cloneId = DB::table('food_specials')->insertGetId([
                    'event_id' => $eventId,
                    'name' => $original->name,
                    'translation_key' => null,
                    'created_at' => $original->created_at,
                    'updated_at' => $original->updated_at,
                ]);

                DB::table('guest_food_special')
                    ->where('food_special_id', $foodSpecialId)
                    ->whereIn('guest_id', function ($q) use ($eventId) {
                        $q->select('id')->from('guests')->where('event_id', $eventId);
                    })
                    ->update(['food_special_id' => $cloneId]);
            }
        }

        // 3. Validation (fail loudly): no guest may reference a non-null,
        //    foreign-event food_special after the backfill.
        $leaks = DB::table('guest_food_special as gfs')
            ->join('guests as g', 'g.id', '=', 'gfs.guest_id')
            ->join('food_specials as fs', 'fs.id', '=', 'gfs.food_special_id')
            ->whereNotNull('fs.event_id')
            ->whereNotNull('g.event_id')
            ->whereColumn('fs.event_id', '!=', 'g.event_id')
            ->count();

        if ($leaks > 0) {
            throw new RuntimeException(
                "food_specials backfill failed: {$leaks} guest(s) still reference a foreign event's food-special. Aborting before the FK is added."
            );
        }

        // 4. Add the FK last — cascadeOnDelete (see class docblock / §13.5 #1).
        Schema::table('food_specials', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('food_specials', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
