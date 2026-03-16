<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Category;
use App\Models\Group;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\FoodSpecial;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $query = Guest::with(['category', 'group', 'drinks', 'foodSpecials']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return Inertia::render('Table', [
            'guests'        => $query->get(),
            'categories'    => Category::orderBy('title', 'asc')->get(['id', 'title']),
            'groups'        => Group::orderBy('name', 'asc')->get(['id', 'name']),
            'food_specials' => FoodSpecial::orderBy('name')->get(['id', 'name']),
            'filters'       => [
                'category_id' => $request->category_id,
            ],
        ]);
    }
}
