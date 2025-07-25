<?php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        Family::create($request->only('name'));

        return redirect()->back()->with('success', 'Familie erstellt!');

    }
}
