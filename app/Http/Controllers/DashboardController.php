<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Guest;
use App\Models\Family;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'badges'   => Badge::orderBy('title', 'asc')->get(['id', 'title']),
            'guests'   => Guest::with('badge', 'family', 'drinks')->get(),
            'families' => Family::orderBy('name', 'asc')->get(['id', 'name']),
        ]);
    }
}
