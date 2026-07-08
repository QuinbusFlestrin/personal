<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Places to see in Switzerland</h1>

    <div class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Type</label>
            <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Canton</label>
            <select wire:model.live="canton" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All cantons</option>
                @foreach ($cantons as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Search</label>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Name..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
    </div>

    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-600">{{ $venues->total() }} place(s) found</p>
        <button wire:click="resetFilters" class="text-sm text-indigo-600 hover:underline">Reset filters</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($venues as $venue)
            <a href="{{ route('venues.show', $venue->slug) }}" class="block bg-white shadow rounded-lg overflow-hidden hover:shadow-md transition">
                @if ($venue->image)
                    <img src="{{ $venue->image }}" alt="{{ $venue->name }}" class="w-full h-40 object-cover">
                @endif
                <div class="p-4">
                    <span class="text-xs font-semibold text-indigo-600 uppercase">{{ $types[$venue->venue_type] ?? $venue->venue_type }}</span>
                    <h2 class="text-lg font-semibold text-gray-900 mt-1">{{ $venue->name }}</h2>
                    @if ($venue->canton)
                        <p class="text-sm text-gray-500 mt-1">{{ $venue->city?->name ? $venue->city->name.', ' : '' }}{{ $venue->canton->name }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-gray-500 col-span-full">No places match your filters.</p>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $venues->links() }}
    </div>
</div>
