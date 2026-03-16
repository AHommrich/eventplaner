<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drink;
use App\Models\DrinkLog;
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
            ->orderBy('name')
            ->get(['id', 'name']);

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

        $drink = Drink::findOrFail($data['drink_id']);
        abort_if($drink->event_id !== $guest->event_id, 403);

        DrinkLog::create([
            'guest_id' => $guest->id,
            'drink_id' => $drink->id,
        ]);

        $total = DrinkLog::where('guest_id', $guest->id)
            ->where('drink_id', $drink->id)
            ->count();

        return response()->json([
            'drink_id'   => $drink->id,
            'drink_name' => $drink->name,
            'total'      => $total,
        ], 201);
    }

    /**
     * GET /api/drinks/stats
     * Statistiken: eigene Bilanz + Event-Rangliste pro Getränk.
     */
    public function stats(Request $request): JsonResponse
    {
        $guest  = $request->user();
        $drinks = Drink::where('event_id', $guest->event_id)->get();

        // Eigene Bilanz
        $myStats = $drinks->map(function ($drink) use ($guest) {
            return [
                'drink_id'   => $drink->id,
                'drink_name' => $drink->name,
                'count'      => DrinkLog::where('guest_id', $guest->id)
                    ->where('drink_id', $drink->id)
                    ->count(),
            ];
        })->filter(fn($d) => $d['count'] > 0)->values();

        // Event-Gesamt pro Getränk
        $eventTotals = $drinks->map(function ($drink) {
            return [
                'drink_id'   => $drink->id,
                'drink_name' => $drink->name,
                'total'      => DrinkLog::where('drink_id', $drink->id)->count(),
            ];
        })->filter(fn($d) => $d['total'] > 0)->values();

        // Top-Trinker pro Getränk
        $leaderboard = $drinks->map(function ($drink) {
            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count')
                ->groupBy('guest_id')
                ->orderByDesc('count')
                ->with('guest:id,firstname,lastname')
                ->limit(5)
                ->get()
                ->map(fn($row) => [
                    'guest_id'   => $row->guest_id,
                    'firstname'  => $row->guest->firstname,
                    'lastname'   => $row->guest->lastname,
                    'count'      => $row->count,
                ]);

            return [
                'drink_id'   => $drink->id,
                'drink_name' => $drink->name,
                'top'        => $top,
            ];
        })->filter(fn($d) => count($d['top']) > 0)->values();

        return response()->json([
            'my_stats'    => $myStats,
            'event_totals' => $eventTotals,
            'leaderboard' => $leaderboard,
        ]);
    }
}
