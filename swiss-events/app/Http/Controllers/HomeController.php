<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $upcomingEvents = Event::published()
            ->upcoming()
            ->with(['venue', 'category', 'canton'])
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        $featuredVenues = Venue::where('status', 'published')
            ->with(['city', 'canton'])
            ->inRandomOrder()
            ->limit(3)
            ->get();

        return view('home', compact('upcomingEvents', 'featuredVenues'));
    }
}
