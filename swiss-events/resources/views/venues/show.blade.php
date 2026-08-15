<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $venue->name }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            @if ($venue->image)
                <img src="{{ $venue->image }}" alt="{{ $venue->name }}" class="w-full h-64 object-cover">
            @endif

            <div class="p-6">
                <span class="text-xs font-semibold text-indigo-600 uppercase">{{ str($venue->venue_type)->replace('_', ' ') }}</span>
                <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $venue->name }}</h1>

                <div class="mt-4 space-y-1 text-gray-700">
                    @if ($venue->address)
                        <p><strong>Address:</strong> {{ $venue->address }}
                            @if ($venue->city), {{ $venue->city->name }}@endif
                            @if ($venue->canton), {{ $venue->canton->name }}@endif
                        </p>
                    @endif
                    @if ($venue->website)
                        <p><a href="{{ $venue->website }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Official website &rarr;</a></p>
                    @endif
                </div>

                @if ($venue->description)
                    <div class="mt-6 prose max-w-none text-gray-800">
                        <p>{{ $venue->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($upcomingEvents->isNotEmpty())
            <div class="mt-10">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Upcoming events here</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($upcomingEvents as $event)
                        <a href="{{ route('events.show', $event->slug) }}" class="block bg-white shadow rounded-lg p-4 hover:shadow-md transition">
                            <h3 class="font-semibold text-gray-900">{{ $event->title }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $event->starts_at->translatedFormat('d M Y H:i') }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-source-attribution :source="$venue->source" />

        <div class="mt-6">
            <a href="{{ route('venues.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to all places</a>
        </div>
    </div>
</x-app-layout>
