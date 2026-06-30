<?php

namespace App\Services;

use App\Models\EventTaskOverride;
use App\Models\PhotoGameTaskCatalog;
use Illuminate\Support\Collection;

/**
 * Baut den Aufgaben-Pool für das Fotospiel auf — Delta-Modell.
 *
 *  1. Base-Katalog (`is_base = true`) liefert die Standard-Aufgaben — immer dabei.
 *  2. Typ-Katalog (`event_photo_games.catalog_id`) liefert event-typische Zusätze
 *     (z.B. „Brautpaar beim ersten Tanz" für Hochzeit) — optional.
 *  3. Event-Overrides modifizieren den so entstandenen Pool:
 *     - `hidden`   → entfernt einen Standard-Task
 *     - `modified` → ersetzt die Beschreibung eines Standard-Tasks
 *     - `added`    → ergänzt eine event-eigene Aufgabe
 *
 * Vorteil: Globale Standard-Aufgaben bleiben an einer Stelle pflegbar, jedes Event
 * speichert nur Deltas — keine Volltext-Duplikate pro Event.
 *
 * Items haben das Shape:
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
