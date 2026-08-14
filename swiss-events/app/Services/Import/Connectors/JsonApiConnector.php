<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic connector for any JSON API: the actual field mapping lives entirely
 * in the source's `config` (dot-notation paths), so onboarding a new JSON
 * source is a data change, not a new class.
 *
 * Expected config shape:
 *   url: string
 *   items_path: string|null  (dot path to the array of items, e.g. "data.events")
 *   field_map: {
 *     external_id, title, description, starts_at, ends_at,
 *     venue_name, venue_address, category_hint, external_url, image, price_info
 *   }
 */
class JsonApiConnector implements SourceConnector
{
    public function fetch(Source $source): iterable
    {
        $config = $source->config ?? [];
        $url = $config['url'] ?? null;
        $fieldMap = $config['field_map'] ?? [];

        if (! $url) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) is missing config.url");
        }

        if (! isset($fieldMap['title'], $fieldMap['starts_at'])) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) config.field_map must at least map title and starts_at");
        }

        $response = Http::timeout(20)->get($url);
        $response->throw();

        $body = $response->json();
        $items = isset($config['items_path']) ? Arr::get($body, $config['items_path'], []) : $body;

        foreach ($items ?? [] as $item) {
            $startsAt = $this->parseDate(Arr::get($item, $fieldMap['starts_at']));

            if ($startsAt === null) {
                continue;
            }

            yield new RawEventDTO(
                externalId: isset($fieldMap['external_id']) ? (string) Arr::get($item, $fieldMap['external_id']) : null,
                title: (string) Arr::get($item, $fieldMap['title']),
                description: isset($fieldMap['description']) ? Arr::get($item, $fieldMap['description']) : null,
                startsAt: $startsAt,
                endsAt: isset($fieldMap['ends_at']) ? $this->parseDate(Arr::get($item, $fieldMap['ends_at'])) : null,
                venueName: isset($fieldMap['venue_name']) ? Arr::get($item, $fieldMap['venue_name']) : null,
                venueAddress: isset($fieldMap['venue_address']) ? Arr::get($item, $fieldMap['venue_address']) : null,
                categoryHint: isset($fieldMap['category_hint']) ? Arr::get($item, $fieldMap['category_hint']) : null,
                externalUrl: isset($fieldMap['external_url']) ? Arr::get($item, $fieldMap['external_url']) : null,
                image: isset($fieldMap['image']) ? Arr::get($item, $fieldMap['image']) : null,
                priceInfo: isset($fieldMap['price_info']) ? Arr::get($item, $fieldMap['price_info']) : null,
            );
        }
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
