<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $event->title }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            @if ($event->image)
                <img src="{{ $event->image }}" alt="{{ $event->title }}" class="w-full h-64 object-cover">
            @endif

            <div class="p-6">
                @if ($event->category)
                    <span class="text-xs font-semibold text-indigo-600 uppercase">{{ $event->category->name }}</span>
                @endif

                <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $event->title }}</h1>

                <div class="mt-4 space-y-1 text-gray-700">
                    <p><strong>When:</strong> {{ $event->starts_at->translatedFormat('l, d F Y H:i') }}
                        @if ($event->ends_at) &ndash; {{ $event->ends_at->translatedFormat('H:i') }} @endif
                    </p>
                    @if ($event->venue)
                        <p>
                            <strong>Where:</strong>
                            <a href="{{ route('venues.show', $event->venue->slug) }}" class="text-indigo-600 hover:underline">{{ $event->venue->name }}</a>
                            @if ($event->canton), {{ $event->canton->name }}@endif
                        </p>
                    @endif
                    @if ($event->price_info)
                        <p><strong>Price:</strong> {{ $event->price_info }}</p>
                    @endif
                    @if ($event->external_url)
                        <p><a href="{{ $event->external_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Official event page &rarr;</a></p>
                    @endif
                </div>

                @if ($event->description)
                    <div class="mt-6 prose max-w-none text-gray-800">
                        <p>{{ $event->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="mt-10">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Related events</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    @foreach ($related as $item)
                        <a href="{{ route('events.show', $item->slug) }}" class="block bg-white shadow rounded-lg p-4 hover:shadow-md transition">
                            <h3 class="font-semibold text-gray-900">{{ $item->title }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $item->starts_at->translatedFormat('d M Y H:i') }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-source-attribution :source="$event->source" />

        <div class="mt-6">
            <a href="{{ route('events.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to all events</a>
        </div>
    </div>
</x-app-layout>
