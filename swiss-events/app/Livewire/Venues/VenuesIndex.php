<?php

namespace App\Livewire\Venues;

use App\Models\Canton;
use App\Models\Venue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Places to see in Switzerland')]
class VenuesIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $canton = null;

    #[Url]
    public ?string $type = null;

    #[Url]
    public string $search = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['canton', 'type', 'search']);
    }

    public function render()
    {
        $venues = Venue::query()
            ->where('status', 'published')
            ->when($this->canton, fn ($q) => $q->where('canton_id', $this->canton))
            ->when($this->type, fn ($q) => $q->where('venue_type', $this->type))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->with(['city', 'canton'])
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.venues.venues-index', [
            'venues' => $venues,
            'cantons' => Canton::orderBy('name')->get(),
            'types' => [
                Venue::TYPE_MUSEUM => 'Museum',
                Venue::TYPE_PARK => 'Park',
                Venue::TYPE_HISTORICAL_BUILDING => 'Historical building',
                Venue::TYPE_THEATRE => 'Theatre / concert hall',
                Venue::TYPE_GENERIC => 'Generic venue',
            ],
        ]);
    }
}
