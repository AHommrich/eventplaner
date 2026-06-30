<?php

namespace App\Http\Controllers;

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkCatalogSize;
use App\Models\DrinkLog;
use App\Services\DrinkScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Veranstalter-Sicht auf Getränke-Katalog + Trinkspiel.
 *
 *  - `index()`        → Katalog-Auswahl: pro Typ N Größen, mit Punkte-Vorschau via {@see DrinkScoreService}
 *  - `batch()`        → Add/Remove mehrerer Sizes auf einmal (UI sendet Diff)
 *  - `store()`/`destroy()` → Einzeloperationen (Legacy / Cleanup)
 *  - `game()`         → Trinkspiel-Auswertung mit Leaderboard pro Getränketyp und Gesamt
 *  - `updateGameSettings()` → Trinkspiel-Endzeitpunkt
 *
 * Punkte werden in `drink_logs.final_points` denormalisiert gespeichert
 * (historische Stabilität), daher hier nur SUM ohne Re-Berechnung.
 */
class DrinkController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        // addedSizeMap: size_id → drink_id (für Löschen per Badge)
        $addedSizeMap = $event
            ? $event->drinks()->pluck('id', 'size_id')->toArray()
            : [];

        // Aktive Getränke: nach Typ gruppiert, mit ausgewählten Größen
        $eventDrinks = $event
            ? $event->drinks()->with(['catalog', 'size'])->get()
                ->groupBy('drink_catalog_id')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'catalog_id' => $first->drink_catalog_id,
                        'type' => $first->catalog?->type,
                        'display_name' => $first->catalog?->display_name,
                        'category' => $first->catalog?->category,
                        'is_alcoholic' => $first->catalog?->is_alcoholic,
                        'selected_sizes' => $group->map(fn ($d) => [
                            'id' => $d->size_id,
                            'drink_id' => $d->id,
                            'amount_liter' => $d->size?->amount_liter,
                            'is_default' => $d->size?->is_default,
                            'points' => DrinkScoreService::basePoints($first->catalog, $d->size?->amount_liter ?? 0.0),
                        ])->sortBy('amount_liter')->values(),
                    ];
                })->sortBy('display_name')->values()
            : collect();

        // Gesamtkatalog: pro Size ein event_drink_id-Flag
        $catalog = DrinkCatalog::where('is_active', true)
            ->with('sizes')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($c) use ($addedSizeMap) {
                $defaultLiter = $c->defaultSize()?->amount_liter ?? 0.0;

                return [
                    'id' => $c->id,
                    'category' => $c->category,
                    'type' => $c->type,
                    'display_name' => $c->display_name,
                    'is_alcoholic' => $c->is_alcoholic,
                    'sizes' => $c->sizes->map(fn ($s) => [
                        'id' => $s->id,
                        'amount_liter' => $s->amount_liter,
                        'is_default' => $s->is_default,
                        'points' => DrinkScoreService::basePoints($c, $s->amount_liter),
                        'event_drink_id' => $addedSizeMap[$s->id] ?? null,
                    ]),
                    'points' => DrinkScoreService::basePoints($c, $defaultLiter),
                ];
            })
            ->groupBy('category');

        $guestStats = $event ? [
            'total' => $event->guests()->count(),
            'confirmed' => $event->guests()->where('rsvp_status', 'accepted')->count(),
            'rsvp_deadline' => $event->rsvp_deadline ? \Carbon\Carbon::parse($event->rsvp_deadline)->toDateString() : null,
            'event_date' => $event->date ? \Carbon\Carbon::parse($event->date)->toDateString() : null,
        ] : ['total' => 0, 'confirmed' => 0, 'rsvp_deadline' => null, 'event_date' => null];

        return Inertia::render('Drinks/Index', [
            'event_drinks' => $eventDrinks,
            'catalog' => $catalog,
            'guest_stats' => $guestStats,
        ]);
    }

    public function batch(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'add' => 'array',
            'add.*' => 'integer|exists:drink_catalog_sizes,id',
            'remove' => 'array',
            'remove.*' => 'integer|exists:drinks,id',
        ]);

        foreach ($data['add'] ?? [] as $sizeId) {
            $size = DrinkCatalogSize::findOrFail($sizeId);
            $exists = Drink::where('event_id', $event->id)->where('size_id', $sizeId)->exists();
            if (! $exists) {
                Drink::create([
                    'event_id' => $event->id,
                    'drink_catalog_id' => $size->catalog_id,
                    'size_id' => $sizeId,
                ]);
            }
        }

        Drink::whereIn('id', $data['remove'] ?? [])
            ->where('event_id', $event->id)
            ->delete();

        return redirect()->back()->with('success', 'Getränke gespeichert.');
    }

    public function store(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'drink_catalog_id' => 'required|integer|exists:drink_catalog,id',
        ]);

        // Doppelt hinzufügen verhindern
        $already = Drink::where('event_id', $event->id)
            ->where('drink_catalog_id', $data['drink_catalog_id'])
            ->exists();

        if ($already) {
            return redirect()->back()->with('error', 'Getränk bereits vorhanden.');
        }

        Drink::create([
            'event_id' => $event->id,
            'drink_catalog_id' => $data['drink_catalog_id'],
        ]);

        return redirect()->back()->with('success', 'Getränk hinzugefügt.');
    }

    public function destroy(Drink $drink)
    {
        $drink->delete();

        return redirect()->back()->with('success', 'Getränk entfernt.');
    }

    public function game()
    {
        $event = $this->activeEvent();
        // drinks: N Zeilen pro Typ (eine pro Größe), nach Typ gruppieren für Statistiken
        $drinks = $event
            ? $event->drinks()->with(['catalog', 'size'])->get()
            : collect();

        $drinksByType = $drinks->groupBy('drink_catalog_id');

        // Gesamt pro Getränk-Typ (alle sizes summieren)
        $eventTotals = $drinksByType->map(function ($group) {
            $first = $group->first();
            $drinkIds = $group->pluck('id')->toArray();
            $total = DrinkLog::whereIn('drink_id', $drinkIds)->count();
            $pts = (int) DrinkLog::whereIn('drink_id', $drinkIds)->sum('final_points');
            $defaultLiter = $first->catalog?->defaultSize()?->amount_liter ?? 0.0;

            return [
                'catalog_id' => $first->drink_catalog_id,
                'type' => $first->catalog?->type,
                'display_name' => $first->catalog?->display_name,
                'points_each' => $first->catalog ? DrinkScoreService::basePoints($first->catalog, $defaultLiter) : 0,
                'total' => $total,
                'points_total' => $pts,
            ];
        })->filter(fn ($r) => $r['total'] > 0)->values();

        // Top-Trinker pro Getränk-Typ
        $leaderboard = $drinksByType->map(function ($group) {
            $first = $group->first();
            $drinkIds = $group->pluck('id')->toArray();
            $defaultLiter = $first->catalog?->defaultSize()?->amount_liter ?? 0.0;
            $top = DrinkLog::whereIn('drink_id', $drinkIds)
                ->selectRaw('guest_id, COUNT(*) as count, SUM(final_points) as points_total')
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'guest_id' => $row->guest_id,
                    'firstname' => $row->guest->firstname,
                    'lastname' => $row->guest->lastname,
                    'count' => $row->count,
                    'points_total' => (int) $row->points_total,
                ]);

            return [
                'catalog_id' => $first->drink_catalog_id,
                'type' => $first->catalog?->type,
                'display_name' => $first->catalog?->display_name,
                'points_each' => $first->catalog ? DrinkScoreService::basePoints($first->catalog, $defaultLiter) : 0,
                'top' => $top,
            ];
        })->filter(fn ($r) => $r['top']->isNotEmpty())->values();

        // Gesamtrangliste
        $drinkIds = $drinks->pluck('id')->toArray();
        $guestTotals = collect();

        if (! empty($drinkIds)) {
            $guestTotals = DrinkLog::whereIn('drink_id', $drinkIds)
                ->selectRaw('guest_id, COUNT(*) as total, SUM(final_points) as points_total')
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(20)
                ->get()
                ->map(fn ($row) => [
                    'guest_id' => $row->guest_id,
                    'firstname' => $row->guest->firstname,
                    'lastname' => $row->guest->lastname,
                    'total' => $row->total,
                    'points_total' => (int) $row->points_total,
                ]);
        }

        // event_drinks für Game-View: nach Typ gruppiert mit ausgewählten Größen
        $eventDrinkList = $drinksByType->map(function ($group) {
            $first = $group->first();

            return [
                'catalog_id' => $first->drink_catalog_id,
                'type' => $first->catalog?->type,
                'display_name' => $first->catalog?->display_name,
                'is_alcoholic' => $first->catalog?->is_alcoholic,
                'selected_sizes' => $group->map(fn ($d) => [
                    'id' => $d->size_id,
                    'drink_id' => $d->id,
                    'amount_liter' => $d->size?->amount_liter,
                    'is_default' => $d->size?->is_default,
                    'points' => DrinkScoreService::basePoints($first->catalog, $d->size?->amount_liter ?? 0.0),
                ])->sortBy('amount_liter')->values(),
            ];
        })->sortBy('display_name')->values();

        return Inertia::render('Drinks/Game', [
            'event_drinks' => $eventDrinkList,
            'event_totals' => $eventTotals,
            'leaderboard' => $leaderboard,
            'guest_totals' => $guestTotals,
            'drink_game_end_time' => $event?->drink_game_end_time
                ? \Carbon\Carbon::parse($event->drink_game_end_time)->format('Y-m-d\TH:i')
                : null,
        ]);
    }

    public function updateGameSettings(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(! $event, 404);

        $data = $request->validate([
            'drink_game_end_time' => 'nullable|date',
        ]);

        $event->update($data);

        return back()->with('success', true);
    }
}
