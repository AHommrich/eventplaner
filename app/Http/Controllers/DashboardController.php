<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Guest;
use App\Models\Group;
use App\Models\FoodSpecial;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'categories'    => Category::orderBy('title', 'asc')->get(['id', 'title']),
            'guests'        => Guest::with(['category', 'group', 'drinks', 'foodSpecials'])
                                ->latest()
                                ->take(10)
                                ->get(),
            'groups'        => Group::orderBy('name', 'asc')->get(['id', 'name']),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
