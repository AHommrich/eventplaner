<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drink;
use App\Models\DrinkLog;
use App\Models\Event;
use App\Services\DrinkScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Drink tracking and drinking-game stats for the app.
 *
 *  - GET  /drinks        → catalog of the event, grouped by type with the sizes
 *                          selected by the organizer; labels localized via Accept-Language
 *  - POST /drinks/log    → log a drink; points via {@see DrinkScoreService}
 *                          (base + streak effect). 60 s cooldown between logs.
 *  - GET  /drinks/stats  → own balance, event totals, top-5 per drink, overall leaderboard
 *
 * `final_points` are stored on the log (denormalized) so historical
 * point totals stay stable when the scoring logic changes.
 */
class DrinkLogController extends Controller
{
    /**
     * GET /api/drinks
     * Available drinks for the event of the logged-in guest.
     */
    private const CATEGORY_ORDER = ['beer', 'wine', 'spirit', 'longdrink', 'cocktail', 'softdrink', 'water', 'coffee'];

    private const CATEGORY_LABELS = [
        'de' => [
            'beer' => 'Bier',
            'wine' => 'Wein',
            'spirit' => 'Shots & Spirituosen',
            'longdrink' => 'Longdrinks',
            'cocktail' => 'Cocktails',
            'softdrink' => 'Softdrinks',
            'water' => 'Wasser',
            'coffee' => 'Kaffee & Tee',
        ],
        'en' => [
            'beer' => 'Beer',
            'wine' => 'Wine',
            'spirit' => 'Shots & Spirits',
            'longdrink' => 'Long Drinks',
            'cocktail' => 'Cocktails',
            'softdrink' => 'Soft Drinks',
            'water' => 'Water',
            'coffee' => 'Coffee & Tea',
        ],
    ];

    /**
     * GET /api/drinks
     * Available drinks for the event of the logged-in guest.
     * Sorted by category (defined order) + display name.
     */
    public function index(Request $request): JsonResponse
    {
        $guest = $request->user();
        $lang = $request->getPreferredLanguage(['de', 'en']);

        $categoryOrder = array_flip(self::CATEGORY_ORDER);
        $categoryLabels = self::CATEGORY_LABELS[$lang] ?? self::CATEGORY_LABELS['de'];

        // drinks: N rows per type — group by catalog_id, only selected sizes
        $drinks = Drink::where('event_id', $guest->event_id)
            ->with(['catalog', 'size'])
            ->get()
            ->groupBy('drink_catalog_id')
            ->map(fn ($group) => [
                'id' => $group->first()->drink_catalog_id,
                'display_name' => ($lang === 'en' && $group->first()->catalog?->display_name_en)
                    ? $group->first()->catalog->display_name_en
                    : $group->first()->catalog?->display_name,
                'category' => $group->first()->catalog?->category,
                'category_label' => $categoryLabels[$group->first()->catalog?->category] ?? $group->first()->catalog?->category,
                'is_alcoholic' => $group->first()->catalog?->is_alcoholic,
                'sizes' => $group->map(fn ($d) => [
                    'drink_id' => $d->id,
                    'id' => $d->size_id,
                    'amount_liter' => $d->size?->amount_liter,
                    'is_default' => $d->size?->is_default,
                    'points' => $d->catalog ? DrinkScoreService::basePoints($d->catalog, $d->size?->amount_liter ?? 0.0) : 0,
                ])->sortBy('amount_liter')->values(),
            ])->values()
            ->sortBy([
                fn ($a, $b) => ($categoryOrder[$a['category']] ?? 99) <=> ($categoryOrder[$b['category']] ?? 99),
                fn ($a, $b) => $a['display_name'] <=> $b['display_name'],
            ])
            ->values();

        return response()->json(['data' => $drinks]);
    }

    /**
     * POST /api/drinks/log
     * Log a drink for the logged-in guest.
     */
    public function log(Request $request): JsonResponse
    {
        $guest = $request->user();

        // check cooldown
        $lastLog = DrinkLog::where('guest_id', $guest->id)
            ->latest()
            ->first();

        if ($lastLog && $lastLog->created_at->diffInSeconds(now()) < DrinkScoreService::COOLDOWN_SECONDS) {
            $remaining = DrinkScoreService::COOLDOWN_SECONDS - $lastLog->created_at->diffInSeconds(now());

            return response()->json([
                'message' => 'Bitte warte noch etwas vor dem nächsten Getränk.',
                'code' => 'cooldown',
                'retry_after' => $remaining,
            ], 429);
        }

        $data = $request->validate([
            'drink_id' => 'required|integer|exists:drinks,id',
        ]);

        $drink = Drink::with(['catalog', 'size'])->findOrFail($data['drink_id']);
        abort_if($drink->event_id !== $guest->event_id, 403);

        $amountLiter = $drink->size?->amount_liter ?? $drink->catalog?->defaultSize()?->amount_liter ?? 0.0;

        // check game time window
        $event = Event::find($guest->event_id);
        if ($event && $event->drink_game_end_time && now()->isAfter($event->drink_game_end_time)) {
            return response()->json(['message' => 'Das Trinkspiel ist beendet.', 'code' => 'game_ended'], 403);
        }

        // calculate points
        $history = DrinkScoreService::guestHistory($guest->id);
        $basePoints = $drink->catalog ? DrinkScoreService::basePoints($drink->catalog, $amountLiter) : 0;
        $finalPoints = $drink->catalog
            ? DrinkScoreService::effectivePoints($drink->catalog, $amountLiter, $history)
            : 0;

        $log = DrinkLog::create([
            'guest_id' => $guest->id,
            'drink_id' => $drink->id,
            'size_id' => $drink->size_id,
            'amount_liter' => $amountLiter,
            'base_points' => $basePoints,
            'final_points' => $finalPoints,
        ]);

        $total = DrinkLog::where('guest_id', $guest->id)->where('drink_id', $drink->id)->count();
        $pointsTotal = DrinkLog::where('guest_id', $guest->id)->where('drink_id', $drink->id)->sum('final_points');
        $bingePenalty = $finalPoints < $basePoints && $drink->catalog?->is_alcoholic;

        return response()->json([
            'drink_id' => $drink->id,
            'display_name' => $drink->catalog?->display_name,
            'total' => $total,
            'base_points' => $basePoints,
            'final_points' => $finalPoints,
            'points_each' => $basePoints,
            'points_total' => $pointsTotal,
            'binge_penalty' => $bingePenalty,
        ], 201);
    }

    /**
     * GET /api/drinks/stats
     * Stats: own balance + event ranking by points.
     * Uses the stored final_points from the logs.
     */
    public function stats(Request $request): JsonResponse
    {
        $guest = $request->user();
        $drinks = Drink::where('event_id', $guest->event_id)->with(['catalog', 'catalog.sizes'])->get();

        // own balance (summed by final_points)
        $myStats = $drinks->map(function ($drink) use ($guest) {
            $defaultLiter = $drink->catalog?->defaultSize()?->amount_liter ?? 0.0;
            $logs = DrinkLog::where('guest_id', $guest->id)->where('drink_id', $drink->id)->get();
            $count = $logs->count();
            $pts = $logs->sum('final_points');

            return [
                'drink_id' => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each' => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog, $defaultLiter) : 0,
                'count' => $count,
                'points_total' => $pts,
            ];
        })->filter(fn ($d) => $d['count'] > 0)->values();

        // event totals per drink (sum of final_points)
        $eventTotals = $drinks->map(function ($drink) {
            $defaultLiter = $drink->catalog?->defaultSize()?->amount_liter ?? 0.0;
            $total = DrinkLog::where('drink_id', $drink->id)->count();
            $pts = DrinkLog::where('drink_id', $drink->id)->sum('final_points');

            return [
                'drink_id' => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each' => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog, $defaultLiter) : 0,
                'total' => $total,
                'points_total' => $pts,
            ];
        })->filter(fn ($d) => $d['total'] > 0)->values();

        // top drinkers per drink (by final_points)
        $leaderboard = $drinks->map(function ($drink) {
            $defaultLiter = $drink->catalog?->defaultSize()?->amount_liter ?? 0.0;
            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count, SUM(final_points) as points_total')
                ->groupBy('guest_id')
                ->orderByDesc('points_total')
                ->with('guest:id,firstname,lastname')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'guest_id' => $row->guest_id,
                    'firstname' => $row->guest->firstname,
                    'lastname' => $row->guest->lastname,
                    'count' => $row->count,
                    'points_total' => (int) $row->points_total,
                ]);

            return [
                'drink_id' => $drink->id,
                'display_name' => $drink->catalog?->display_name,
                'points_each' => $drink->catalog ? DrinkScoreService::basePoints($drink->catalog, $defaultLiter) : 0,
                'top' => $top,
            ];
        })->filter(fn ($d) => count($d['top']) > 0)->values();

        // overall ranking by final_points
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

        // streak of the current guest
        $history = DrinkScoreService::guestHistory($guest->id);
        $currentStreak = DrinkScoreService::currentStreak($history);
        $bingePenalty = $currentStreak >= DrinkScoreService::BINGE_STREAK_THRESHOLD;

        return response()->json([
            'my_stats' => $myStats,
            'event_totals' => $eventTotals,
            'leaderboard' => $leaderboard,
            'guest_totals' => $guestTotals,
            'current_streak' => $currentStreak,
            'binge_penalty' => $bingePenalty,
            'cooldown_seconds' => DrinkScoreService::COOLDOWN_SECONDS,
        ]);
    }
}
