<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodSpecial;

class FoodSpecialController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        FoodSpecial::create(['name' => $request->name]);
        return redirect()->back()->with('success', 'Food Special erstellt!');
    }
}
