<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Badge;
use App\Models\Family;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::with('badge', 'family', 'drinks');

        // Filter nach Badge-ID
        if ($request->filled('badge_id')) {
            $query->where('badge_id', $request->badge_id);
        }

        return Inertia::render('Table', [
            'guests'   => $query->get(),
            'badges'   => Badge::orderBy('title', 'asc')->get(['id', 'title']),
            'families' => Family::orderBy('name', 'asc')->get(['id', 'name']),
            'filters'  => [
                'badge_id' => $request->badge_id,
            ],
        ]);
    }
}
