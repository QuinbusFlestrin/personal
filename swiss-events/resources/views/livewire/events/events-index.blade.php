<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Events in Switzerland</h1>

    <div class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <select wire:model.live="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
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

        <div>
            <label class="block text-sm font-medium text-gray-700">From</label>
            <input type="date" wire:model.live="from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" wire:model.live="to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Search</label>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Keyword..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
    </div>

    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-600">{{ $events->total() }} event(s) found</p>
        <button wire:click="resetFilters" class="text-sm text-indigo-600 hover:underline">Reset filters</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($events as $event)
            <a href="{{ route('events.show', $event->slug) }}" class="block bg-white shadow rounded-lg overflow-hidden hover:shadow-md transition">
                @if ($event->image)
                    <img src="{{ $event->image }}" alt="{{ $event->title }}" class="w-full h-40 object-cover">
                @endif
                <div class="p-4">
                    @if ($event->category)
                        <span class="text-xs font-semibold text-indigo-600 uppercase">{{ $event->category->name }}</span>
                    @endif
                    <h2 class="text-lg font-semibold text-gray-900 mt-1">{{ $event->title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ $event->starts_at->translatedFormat('D, d M Y H:i') }}</p>
                    @if ($event->venue)
                        <p class="text-sm text-gray-500">{{ $event->venue->name }}@if ($event->canton), {{ $event->canton->name }}@endif</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-gray-500 col-span-full">No events match your filters.</p>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $events->links() }}
    </div>
</div>
