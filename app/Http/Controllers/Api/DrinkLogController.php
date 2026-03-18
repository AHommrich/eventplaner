<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drink;
use App\Models\DrinkLog;
use App\Services\DrinkScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrinkLogController extends Controller
{
    /**
     * GET /api/drinks
     * Verfügbare Getränke für das Event des eingeloggten Gastes.
     */
    public function index(Request $request): JsonResponse
    {
        $guest = $request->user();

        $drinks = Drink::where('event_id', $guest->event_id)
            ->with('catalog')
            ->get()
            ->map(fn($d) => [
                'id'           => $d->id,
                'display_name' => $d->catalog?->display_name,
                'category'     => $d->catalog?->category,
                'is_alcoholic' => $d->catalog?->is_alcoholic,
                'amount_liter' => $d->catalog?->amount_liter,
                'points'       => $d->catalog ? DrinkScoreService::basePoints($d->catalog) : 0,
            ])
            ->sortBy('display_name')
            ->values();

        return response()->json(['data' => $drinks]);
    }

    /**
     * POST /api/drinks/log
     * Ein Getränk für den eingeloggten Gast eintragen.
     */
    public function log(Request $request): JsonResponse
    {
        $guest = $request->user();

        $data = $request->validate([
            'drink_id' => 'required|integer|exists:drinks,id',
        ]);

        $drink = Drink::with('catalog')->findOrFail($data['drink_id']);
        abort_if($drink->event_id !== $guest->event_id, 403);

        DrinkLog::create([
            'guest_id' => $guest->id,
            'drink_id' => $drink->id,
        ]);

        $total  = DrinkLog::where('guest_id', $guest->id)->where('drink_id', $drink->id)->count();
        $points = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0;

        return response()->json([
            'drink_id'     => $drink->id,
            'display_name' => $drink->catalog?->display_name,
            'total'        => $total,
            'points_each'  => $points,
            'points_total' => $total * $points,
        ], 201);
    }

    /**
     * GET /api/drinks/stats
     * Statistiken: eigene Bilanz + Event-Rangliste nach Punkten.
     */
    public function stats(Request $request): JsonResponse
    {
        $guest  = $request->user();
        $drinks = Drink::where('event_id', $guest->event_id)->with('catalog')->get();

        // Eigene Bilanz
        $myStats = $drinks->map(function ($drink) use ($guest) {
            $count  = DrinkLog::where('guest_id', $guest->id)->where('drink_id', $drink->id)->count();
            $points = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0;
            return [
                'drink_id'     => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each'  => $points,
                'count'        => $count,
                'points_total' => $count * $points,
            ];
        })->filter(fn($d) => $d['count'] > 0)->values();

        // Event-Gesamt pro Getränk
        $eventTotals = $drinks->map(function ($drink) {
            $total  = DrinkLog::where('drink_id', $drink->id)->count();
            $points = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0;
            return [
                'drink_id'     => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each'  => $points,
                'total'        => $total,
                'points_total' => $total * $points,
            ];
        })->filter(fn($d) => $d['total'] > 0)->values();

        // Top-Trinker pro Getränk
        $leaderboard = $drinks->map(function ($drink) {
            $pts = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog) : 0;
            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count, COUNT(*) * ? as points_total', [$pts])
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(5)
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
        })->filter(fn($d) => count($d['top']) > 0)->values();

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

        return response()->json([
            'my_stats'     => $myStats,
            'event_totals' => $eventTotals,
            'leaderboard'  => $leaderboard,
            'guest_totals' => $guestTotals,
        ]);
    }
}
