<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FoodSpecial;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $event = $this->activeEvent();

        $query = $event
            ? $event->guests()->with(['category', 'group', 'foodSpecials'])
            : \App\Models\Guest::with(['category', 'group', 'foodSpecials'])->whereNull('id');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return Inertia::render('Table', [
            'guests'        => $query->get(),
            'categories'    => Category::orderBy('title', 'asc')->get(['id', 'title']),
            'groups'        => $event ? $event->groups()->orderBy('name')->get(['id', 'name']) : collect(),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
            'filters'       => ['category_id' => $request->category_id],
        ]);
    }
}
