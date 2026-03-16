<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FoodSpecial;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $event = $this->activeEvent();

        return Inertia::render('Dashboard', [
            'categories'    => Category::orderBy('title', 'asc')->get(['id', 'title']),
            'guests'        => $event
                                ? $event->guests()->with(['category', 'group', 'drinks', 'foodSpecials'])->latest()->take(10)->get()
                                : collect(),
            'groups'        => $event
                                ? $event->groups()->orderBy('name')->get(['id', 'name'])
                                : collect(),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
