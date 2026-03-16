<?php

namespace App\Http\Controllers;

use App\Models\Drink;
use App\Models\DrinkLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DrinkController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        return Inertia::render('Drinks/Index', [
            'drinks' => $event ? $event->drinks()->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $event = $this->activeEvent();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Drink::create([
            'event_id' => $event?->id,
            'name'     => $data['name'],
        ]);

        return redirect()->back()->with('success', 'Getränk hinzugefügt.');
    }

    public function destroy(Drink $drink)
    {
        $drink->delete();
        return redirect()->back()->with('success', 'Getränk gelöscht.');
    }

    public function stats()
    {
        $event  = $this->activeEvent();
        $drinks = $event ? $event->drinks()->orderBy('name')->get() : collect();

        // Gesamt pro Getränk
        $eventTotals = $drinks->map(fn($drink) => [
            'drink_id'   => $drink->id,
            'drink_name' => $drink->name,
            'total'      => DrinkLog::where('drink_id', $drink->id)->count(),
        ]);

        // Top-Trinker pro Getränk (Top 10)
        $leaderboard = $drinks->map(function ($drink) {
            $top = DrinkLog::where('drink_id', $drink->id)
                ->selectRaw('guest_id, COUNT(*) as count')
                ->groupBy('guest_id')
                ->orderByDesc('count')
                ->with('guest:id,firstname,lastname')
                ->limit(10)
                ->get()
                ->map(fn($row) => [
                    'guest_id'  => $row->guest_id,
                    'firstname' => $row->guest->firstname,
                    'lastname'  => $row->guest->lastname,
                    'count'     => $row->count,
                ]);

            return [
                'drink_id'   => $drink->id,
                'drink_name' => $drink->name,
                'top'        => $top,
            ];
        });

        // Gesamtrangliste: wer hat insgesamt am meisten getrunken
        $guestTotals = DrinkLog::whereHas('drink', fn($q) => $q->where('event_id', $event?->id))
            ->selectRaw('guest_id, COUNT(*) as total')
            ->groupBy('guest_id')
            ->orderByDesc('total')
            ->with('guest:id,firstname,lastname')
            ->limit(20)
            ->get()
            ->map(fn($row) => [
                'guest_id'  => $row->guest_id,
                'firstname' => $row->guest->firstname,
                'lastname'  => $row->guest->lastname,
                'total'     => $row->total,
            ]);

        return Inertia::render('Drinks/Stats', [
            'event_totals' => $eventTotals,
            'leaderboard'  => $leaderboard,
            'guest_totals' => $guestTotals,
        ]);
    }
}
