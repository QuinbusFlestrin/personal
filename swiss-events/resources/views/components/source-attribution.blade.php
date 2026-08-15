@props(['source'])

@php($attribution = $source?->attribution())

@if ($attribution)
    <p class="mt-6 text-xs text-gray-500">
        Data source:
        @if ($attribution['url'])
            <a href="{{ $attribution['url'] }}" rel="noopener nofollow" class="underline hover:text-gray-700">{{ $attribution['text'] }}</a>
        @else
            {{ $attribution['text'] }}
        @endif

        @if ($attribution['licence'])
            &middot;
            @if ($attribution['licence_url'])
                <a href="{{ $attribution['licence_url'] }}" rel="license noopener nofollow" class="underline hover:text-gray-700">{{ $attribution['licence'] }}</a>
            @else
                {{ $attribution['licence'] }}
            @endif
        @endif
    </p>
@endif
