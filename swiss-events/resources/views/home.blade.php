<x-app-layout>
    <div class="bg-indigo-700 text-white">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">Everything happening in Switzerland</h1>
            <p class="mt-4 text-indigo-100 max-w-2xl mx-auto">Concerts, festivals, exhibitions, family events, museums, parks and more &mdash; browse by type, location and date.</p>
            <div class="mt-8 flex justify-center gap-4">
                <a href="{{ route('events.index') }}" class="bg-white text-indigo-700 font-semibold px-6 py-3 rounded-md hover:bg-indigo-50">Browse events</a>
                <a href="{{ route('venues.index') }}" class="bg-indigo-600 text-white font-semibold px-6 py-3 rounded-md hover:bg-indigo-500">Discover places</a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-900">Upcoming events</h2>
            <a href="{{ route('events.index') }}" class="text-sm text-indigo-600 hover:underline">See all &rarr;</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($upcomingEvents as $event)
                <a href="{{ route('events.show', $event->slug) }}" class="block bg-white shadow rounded-lg p-4 hover:shadow-md transition">
                    @if ($event->category)
                        <span class="text-xs font-semibold text-indigo-600 uppercase">{{ $event->category->name }}</span>
                    @endif
                    <h3 class="font-semibold text-gray-900 mt-1">{{ $event->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $event->starts_at->translatedFormat('d M Y H:i') }}</p>
                    @if ($event->venue)
                        <p class="text-sm text-gray-500">{{ $event->venue->name }}</p>
                    @endif
                </a>
            @empty
                <p class="text-gray-500 col-span-full">No upcoming events yet &mdash; check back soon.</p>
            @endforelse
        </div>
    </div>

    @if ($featuredVenues->isNotEmpty())
        <div class="bg-gray-50">
            <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Places to explore</h2>
                    <a href="{{ route('venues.index') }}" class="text-sm text-indigo-600 hover:underline">See all &rarr;</a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    @foreach ($featuredVenues as $venue)
                        <a href="{{ route('venues.show', $venue->slug) }}" class="block bg-white shadow rounded-lg p-4 hover:shadow-md transition">
                            <h3 class="font-semibold text-gray-900">{{ $venue->name }}</h3>
                            @if ($venue->canton)
                                <p class="text-sm text-gray-500 mt-1">{{ $venue->canton->name }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
