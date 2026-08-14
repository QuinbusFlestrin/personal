<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventController extends Controller
{
    public function show(Event $event): View
    {
        abort_unless($event->status === Event::STATUS_PUBLISHED, 404);

        $event->load(['venue', 'category', 'canton', 'tags']);

        $related = Event::published()
            ->upcoming()
            ->where('id', '!=', $event->id)
            ->when($event->category_id, fn ($q) => $q->where('category_id', $event->category_id))
            ->orderBy('starts_at')
            ->limit(3)
            ->get();

        return view('events.show', compact('event', 'related'));
    }
}
