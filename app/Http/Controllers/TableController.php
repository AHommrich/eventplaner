<?php

namespace App\Http\Controllers;

use App\Models\FoodSpecial;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $event = $this->activeEvent();

        $query = $event
            ? $event->guests()->with(['group', 'foodSpecials'])
            : \App\Models\Guest::with(['group', 'foodSpecials'])->whereNull('id');

        return Inertia::render('Guests', [
            'guests'        => $query->get(),
            'groups'        => $event ? $event->groups()->orderBy('name')->get(['id', 'name']) : collect(),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
