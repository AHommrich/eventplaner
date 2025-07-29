<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Guest;
use App\Models\Family;
use App\Models\FoodSpecial;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'badges'        => Badge::orderBy('title', 'asc')->get(['id', 'title']),
            'guests'        => Guest::with('badge', 'family', 'drinks', 'foodSpecials')->get(),
            'families'      => Family::orderBy('name', 'asc')->get(['id', 'name']),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']), // <- wichtig
        ]);
    }
}
