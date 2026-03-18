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
                'display_name' => $d->catalog?->display_name,
                'category'     => $d->catalog?->category,
                'is_alcoholic' => $d->catalog?->is_alcoholic,
                'points'       => $d->catalog ? DrinkScoreService::basePoints($d->catalog) : 0,
            ])->sortBy('display_name')->values()
            : collect();

        // Gesamtkatalog gruppiert nach Kategorie
        $addedCatalogIds = $eventDrinks->pluck('catalog_id')->toArray();
        $catalog = DrinkCatalog::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'category'       => $c->category,
                'display_name'   => $c->display_name,
                'is_alcoholic'   => $c->is_alcoholic,
                'points'         => DrinkScoreService::basePoints($c),
                'already_added'  => in_array($c->id, $addedCatalogIds),
            ])
            ->groupBy('category');

        return Inertia::render('Drinks/Index', [
            'event_drinks' => $eventDrinks,
            'catalog'      => $catalog,
        ]);
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

        // Gesamt pro Getränk
        $eventTotals = $drinks->map(fn($drink) => [
            'drink_id'     => $drink->id,
            'display_name' => $drink->catalog?->display_name,
            'points_each'  => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0,
            'total'        => DrinkLog::where('drink_id', $drink->id)->count(),
            'points_total' => DrinkLog::where('drink_id', $drink->id)->count()
                              * ($drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0),
        ])->filter(fn($r) => $r['total'] > 0)->values();

        // Top-Trinker pro Getränk (Top 10)
        $leaderboard = $drinks->map(function ($drink) {
            $pts = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0;

            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count, COUNT(*) * ? as points_total', [$pts])
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
                    'points_total' => $row->points_total,
                ]);

            return [
                'drink_id'    => $drink->id,
                'display_name'=> $drink->catalog?->display_name,
                'points_each' => $pts,
                'top'         => $top,
            ];
        })->filter(fn($r) => $r['top']->isNotEmpty())->values();

        // Gesamtrangliste nach Punkten
        $drinkIds = $drinks->pluck('id')->toArray();
        $guestTotals = collect();

        if (!empty($drinkIds)) {
            $guestTotals = DrinkLog::whereIn('drink_id', $drinkIds)
                ->join('drinks', 'drink_logs.drink_id', '=', 'drinks.id')
                ->join('drink_catalog', 'drinks.drink_catalog_id', '=', 'drink_catalog.id')
                ->selectRaw('drink_logs.guest_id, COUNT(*) as total, SUM(
                    CASE WHEN drink_catalog.is_alcoholic = 1
                        THEN ROUND((drink_catalog.amount_liter * drink_catalog.alcohol_percent) * 10)
                        ELSE COALESCE(drink_catalog.negative_points, 0)
                    END
                ) as points_total')
                ->groupBy('drink_logs.guest_id')
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
            'display_name' => $d->catalog?->display_name,
            'is_alcoholic' => $d->catalog?->is_alcoholic,
            'points'       => $d->catalog ? DrinkScoreService::basePoints($d->catalog) : 0,
        ])->sortBy('display_name')->values();

        return Inertia::render('Drinks/Game', [
            'event_drinks' => $eventDrinkList,
            'event_totals' => $eventTotals,
            'leaderboard'  => $leaderboard,
            'guest_totals' => $guestTotals,
        ]);
    }
}
