<?php

namespace App\Livewire\Events;

use App\Models\Canton;
use App\Models\Category;
use App\Models\Event;
use App\Support\EventFilterQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Events in Switzerland')]
class EventsIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $category = null;

    #[Url]
    public ?int $canton = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['category', 'canton', 'from', 'to', 'search']);
    }

    public function render()
    {
        $events = EventFilterQuery::apply(Event::query(), [
            'category_ids' => $this->category ? [$this->category] : [],
            'canton_ids' => $this->canton ? [$this->canton] : [],
            'from' => $this->from,
            'to' => $this->to,
            'search' => $this->search,
        ])
            ->with(['venue', 'category', 'canton'])
            ->orderBy('starts_at')
            ->paginate(12);

        return view('livewire.events.events-index', [
            'events' => $events,
            'categories' => Category::orderBy('sort_order')->get(),
            'cantons' => Canton::orderBy('name')->get(),
        ]);
    }
}
