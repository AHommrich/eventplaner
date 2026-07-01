<?php

namespace App\Services;

use App\Models\EventTaskOverride;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Support\Collection;

/**
 * Builds the task pool for the photo game — delta model.
 *
 *  1. Base catalog (`is_base = true`) provides the standard tasks — always included.
 *  2. Type catalog (`event_photo_games.catalog_id`) provides event-typical extras
 *     (e.g. „Brautpaar beim ersten Tanz" for a wedding) — optional.
 *  3. Event overrides modify the resulting pool:
 *     - `hidden`   → removes a standard task
 *     - `modified` → replaces the description of a standard task
 *     - `added`    → adds an event-specific task
 *
 * Advantage: global standard tasks stay maintainable in one place, each event
 * only stores deltas — no full-text duplicates per event.
 *
 * Items have the shape:
 *   ['task_id' => ?int, 'override_id' => ?int, 'description' => string,
 *    'description_en' => ?string, 'translation_key' => ?string]
 */
class PhotoGameTaskPool
{
    /**
     * @return Collection<int, array{task_id: ?int, override_id: ?int, description: string, description_en: ?string, translation_key: ?string}>
     */
    public function build(int $eventId, ?int $typeCatalogId): Collection
    {
        $baseCatalog = PhotoGameTaskCatalog::base()->first();
        $pool = $baseCatalog
            ? $baseCatalog->tasks()->active()->get()->map($this->toItem(...))
            : collect();

        if ($typeCatalogId) {
            $typeCatalog = PhotoGameTaskCatalog::find($typeCatalogId);
            if ($typeCatalog) {
                $pool = $pool->concat($typeCatalog->tasks()->active()->get()->map($this->toItem(...)));
            }
        }

        $overrides = EventTaskOverride::where('event_id', $eventId)->get();

        foreach ($overrides as $ov) {
            $pool = match ($ov->action) {
                'hidden' => $pool->reject(fn ($t) => $t['task_id'] === $ov->task_id),
                'modified' => $pool->map(fn ($t) => $t['task_id'] === $ov->task_id
                    ? array_merge($t, ['description' => $ov->custom_text, 'description_en' => null])
                    : $t),
                'added' => $pool->push([
                    'task_id' => null,
                    'override_id' => $ov->id,
                    'description' => $ov->custom_text,
                    'description_en' => null,
                    'translation_key' => null,
                ]),
                default => $pool,
            };
        }

        return $pool->values();
    }

    /**
     * @param  \App\Models\PhotoGameTask  $t
     * @return array{task_id: int, override_id: null, description: string, description_en: ?string, translation_key: ?string}
     */
    private function toItem($t): array
    {
        return [
            'task_id' => $t->id,
            'override_id' => null,
            'description' => $t->description,
            'description_en' => $t->description_en,
            'translation_key' => $t->translation_key,
        ];
    }
}
