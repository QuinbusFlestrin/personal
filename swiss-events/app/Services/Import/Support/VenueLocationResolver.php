<?php

namespace App\Services\Import\Support;

use App\Models\Canton;
use App\Models\City;
use Illuminate\Support\Str;

/**
 * Works out which city/canton a venue sits in from whatever free text a source
 * gives us (an address line, sometimes only the venue name).
 *
 * Imported events denormalize `canton_id` off their venue, and canton is the
 * main filter on /events — so a venue with no canton is effectively invisible
 * to browsing. Feeds rarely publish a canton field, hence this matching.
 *
 * Lookups are loaded once and reused: one import run resolves thousands of
 * events, and the reference tables are tiny and static.
 */
class VenueLocationResolver
{
    /** @var list<array{name: string, city_id: int, canton_id: int}>|null */
    private ?array $cities = null;

    /** @var list<array{name: string, code: string, canton_id: int}>|null */
    private ?array $cantons = null;

    /**
     * Exonyms and local variants that don't survive accent-stripping, mapped
     * onto the spelling used in the cities/cantons tables.
     */
    private const ALIASES = [
        'geneva' => 'geneve',
        'genf' => 'geneve',
        'ginevra' => 'geneve',
        'lucerne' => 'luzern',
        'lucerna' => 'luzern',
        'berne' => 'bern',
        'berna' => 'bern',
        'basle' => 'basel',
        'bale' => 'basel',
        'basilea' => 'basel',
        'bienne' => 'biel',
        'freiburg' => 'fribourg',
        'sitten' => 'sion',
        'coire' => 'chur',
        'coira' => 'chur',
        'saint-gall' => 'st gallen',
        'san gallo' => 'st gallen',
        'sankt gallen' => 'st gallen',
        'losanna' => 'lausanne',
        'zurigo' => 'zurich',
    ];

    public function __construct(private readonly CantonLocator $locator) {}

    /**
     * Coordinates, when present, are authoritative — several sources publish
     * a point and no address whatsoever.
     *
     * @return array{city_id: ?int, canton_id: ?int}
     */
    public function resolve(
        ?string $address,
        ?string $venueName = null,
        ?float $lat = null,
        ?float $lng = null,
    ): array {
        $haystack = $this->normalize(trim(($address ?? '').' '.($venueName ?? '')));

        if ($haystack !== '') {
            foreach ($this->cities() as $city) {
                if ($this->containsWord($haystack, $city['name'])) {
                    return ['city_id' => $city['city_id'], 'canton_id' => $city['canton_id']];
                }
            }

            foreach ($this->cantons() as $canton) {
                if ($this->containsWord($haystack, $canton['name'])) {
                    return ['city_id' => null, 'canton_id' => $canton['canton_id']];
                }
            }
        }

        return [
            'city_id' => null,
            'canton_id' => $this->matchCantonCode($address ?? '') ?? $this->matchCoordinates($lat, $lng),
        ];
    }

    private function matchCoordinates(?float $lat, ?float $lng): ?int
    {
        $code = $this->locator->codeFor($lat, $lng);

        if ($code === null) {
            return null;
        }

        foreach ($this->cantons() as $canton) {
            if ($canton['code'] === $code) {
                return $canton['canton_id'];
            }
        }

        return null;
    }

    /**
     * Canton codes are only two letters, so they're matched against the raw
     * text and only when genuinely uppercase ("1200 GE") — lowercasing first
     * would make "ge" match inside ordinary words.
     */
    private function matchCantonCode(string $address): ?int
    {
        if (! preg_match_all('/\b([A-Z]{2})\b/', $address, $matches)) {
            return null;
        }

        foreach ($matches[1] as $code) {
            foreach ($this->cantons() as $canton) {
                if ($canton['code'] === $code) {
                    return $canton['canton_id'];
                }
            }
        }

        return null;
    }

    private function containsWord(string $haystack, string $needle): bool
    {
        return (bool) preg_match('/(?:^|[^a-z0-9])'.preg_quote($needle, '/').'(?:$|[^a-z0-9])/', $haystack);
    }

    private function normalize(string $value): string
    {
        // Str::ascii folds ü→u, é→e etc. so "Zürich" and "Zurich" compare equal.
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        $value = trim($value);

        foreach (self::ALIASES as $alias => $canonical) {
            $value = preg_replace(
                '/(?:^|(?<=[^a-z0-9]))'.preg_quote($alias, '/').'(?:$|(?=[^a-z0-9]))/',
                $canonical,
                $value
            ) ?? $value;
        }

        return $value;
    }

    /**
     * @return list<array{name: string, city_id: int, canton_id: int}>
     */
    private function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        $cities = City::query()
            ->get(['id', 'name', 'canton_id'])
            ->map(fn (City $city) => [
                'name' => $this->normalize($city->name),
                'city_id' => $city->id,
                'canton_id' => $city->canton_id,
            ])
            ->filter(fn (array $city) => $city['name'] !== '')
            ->all();

        // Longest first, so "St. Gallen" wins over a shorter substring match.
        usort($cities, fn ($a, $b) => strlen($b['name']) <=> strlen($a['name']));

        return $this->cities = array_values($cities);
    }

    /**
     * @return list<array{name: string, code: string, canton_id: int}>
     */
    private function cantons(): array
    {
        if ($this->cantons !== null) {
            return $this->cantons;
        }

        $cantons = Canton::query()
            ->get(['id', 'name', 'code'])
            ->map(fn (Canton $canton) => [
                'name' => $this->normalize($canton->name),
                'code' => $canton->code,
                'canton_id' => $canton->id,
            ])
            ->all();

        usort($cantons, fn ($a, $b) => strlen($b['name']) <=> strlen($a['name']));

        return $this->cantons = array_values($cantons);
    }
}
