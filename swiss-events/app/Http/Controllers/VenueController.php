<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Contracts\View\View;

class VenueController extends Controller
{
    public function show(Venue $venue): View
    {
        abort_unless($venue->status === 'published', 404);

        $venue->load(['city', 'canton']);

        $upcomingEvents = $venue->events()
            ->published()
            ->upcoming()
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        return view('venues.show', compact('venue', 'upcomingEvents'));
    }
}
