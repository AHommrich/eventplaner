<?php

namespace App\Http\Controllers;

use App\Models\Drink;
use App\Models\DrinkCatalog;
use App\Models\DrinkLog;
use App\Services\DrinkScoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DrinkController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        // Bereits dem Event hinzugefügte Getränke
        $eventDrinks = $event
            ? $event->drinks()->with('catalog')->get()->map(fn($d) => [
                'id'           => $d->id,
                'catalog_id'   => $d->drink_catalog_id,
                'type'         => $d->catalog?->type,
                'display_name' => $d->catalog?->display_name,
                'amount_liter' => $d->catalog?->amount_liter,
                'category'     => $d->catalog?->category,
                'is_alcoholic' => $d->catalog?->is_alcoholic,
                'points'       => $d->catalog ? DrinkScoreService::basePoints($d->catalog) : 0,
            ])->sortBy('display_name')->values()
            : collect();

        // Gesamtkatalog gruppiert nach Kategorie
        // addedCatalogMap: catalog_id → event_drink_id (für Löschen per Badge)
        $addedCatalogMap = $event
            ? $event->drinks()->pluck('id', 'drink_catalog_id')->toArray()
            : [];

        $catalog = DrinkCatalog::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => [
                'id'              => $c->id,
                'category'        => $c->category,
                'type'            => $c->type,
                'display_name'    => $c->display_name,
                'amount_liter'    => $c->amount_liter,
                'is_alcoholic'    => $c->is_alcoholic,
                'points'          => DrinkScoreService::basePoints($c),
                'event_drink_id'  => $addedCatalogMap[$c->id] ?? null,
            ])
            ->groupBy('category');

        return Inertia::render('Drinks/Index', [
            'event_drinks' => $eventDrinks,
            'catalog'      => $catalog,
        ]);
    }

    public function batch(Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'add'    => 'array',
            'add.*'  => 'integer|exists:drink_catalog,id',
            'remove' => 'array',
            'remove.*' => 'integer|exists:drinks,id',
        ]);

        foreach ($data['add'] ?? [] as $catalogId) {
            $exists = Drink::where('event_id', $event->id)
                ->where('drink_catalog_id', $catalogId)
                ->exists();
            if (!$exists) {
                Drink::create(['event_id' => $event->id, 'drink_catalog_id' => $catalogId]);
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
        abort_if(!$event, 404);

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
            'event_id'         => $event->id,
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
        $event  = $this->activeEvent();
        $drinks = $event
            ? $event->drinks()->with('catalog')->get()
            : collect();

        // Gesamt pro Getränk (gespeicherte final_points summieren)
        $eventTotals = $drinks->map(fn($drink) => [
            'drink_id'     => $drink->id,
            'display_name' => $drink->catalog?->display_name,
            'points_each'  => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0,
            'total'        => DrinkLog::where('drink_id', $drink->id)->count(),
            'points_total' => (int) DrinkLog::where('drink_id', $drink->id)->sum('final_points'),
        ])->filter(fn($r) => $r['total'] > 0)->values();

        // Top-Trinker pro Getränk (nach gespeicherten final_points, Top 10)
        $leaderboard = $drinks->map(function ($drink) {
            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count, SUM(final_points) as points_total')
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(10)
                ->get()
                ->map(fn($row) => [
                    'guest_id'     => $row->guest_id,
                    'firstname'    => $row->guest->firstname,
                    'lastname'     => $row->guest->lastname,
                    'count'        => $row->count,
                    'points_total' => (int) $row->points_total,
                ]);

            return [
                'drink_id'     => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each'  => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0,
                'top'          => $top,
            ];
        })->filter(fn($r) => $r['top']->isNotEmpty())->values();

        // Gesamtrangliste nach gespeicherten final_points
        $drinkIds    = $drinks->pluck('id')->toArray();
        $guestTotals = collect();

        if (!empty($drinkIds)) {
            $guestTotals = DrinkLog::whereIn('drink_id', $drinkIds)
                ->selectRaw('guest_id, COUNT(*) as total, SUM(final_points) as points_total')
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(20)
                ->get()
                ->map(fn($row) => [
                    'guest_id'     => $row->guest_id,
                    'firstname'    => $row->guest->firstname,
                    'lastname'     => $row->guest->lastname,
                    'total'        => $row->total,
                    'points_total' => (int) $row->points_total,
                ]);
        }

        $eventDrinkList = $drinks->map(fn($d) => [
            'id'           => $d->id,
            'type'         => $d->catalog?->type,
            'display_name' => $d->catalog?->display_name,
            'amount_liter' => $d->catalog?->amount_liter,
            'is_alcoholic' => $d->catalog?->is_alcoholic,
            'points'       => $d->catalog ? DrinkScoreService::basePoints($d->catalog) : 0,
        ])->sortBy('display_name')->values();

        return Inertia::render('Drinks/Game', [
            'event_drinks'        => $eventDrinkList,
            'event_totals'        => $eventTotals,
            'leaderboard'         => $leaderboard,
            'guest_totals'        => $guestTotals,
            'drink_game_enabled'  => (bool) $event?->drink_game_enabled,
            'drink_game_end_time' => $event?->drink_game_end_time?->format('Y-m-d\TH:i'),
        ]);
    }

    public function updateGameSettings(\Illuminate\Http\Request $request)
    {
        $event = $this->activeEvent();
        abort_if(!$event, 404);

        $data = $request->validate([
            'drink_game_enabled'  => 'boolean',
            'drink_game_end_time' => 'nullable|date',
        ]);

        $event->update($data);

        return redirect()->route('drinks.game')->with('success', 'Spieleinstellungen gespeichert.');
    }
}
