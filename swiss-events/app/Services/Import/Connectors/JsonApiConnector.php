<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Connectors\Concerns\FetchesPaginatedJson;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
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
 *   headers: {name: value}|null   value may be "secret:<name>" — see config/services.php
 *   query: {name: value}|null     fixed query params (language, filters, …)
 *   user_agent: string|null
 *   pagination: {
 *     mode: "page"|"offset"|"link",
 *     param: string|null       page/offset parameter name (page & offset modes)
 *     size_param: string|null  page-size parameter name
 *     page_size: int|null
 *     start: int|null          first page number (defaults 1 for page, 0 for offset)
 *     next_path: string|null   dot path to the next-page URL (link mode)
 *     max_pages: int|null      hard stop, defaults to 1 (i.e. no pagination)
 *   }
 */
class JsonApiConnector implements SourceConnector
{
    use FetchesPaginatedJson;

    public function fetch(Source $source): iterable
    {
        $fieldMap = $source->config['field_map'] ?? [];

        if (! isset($fieldMap['title'], $fieldMap['starts_at'])) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) config.field_map must at least map title and starts_at");
        }

        foreach ($this->jsonItems($source) as $item) {
            $dto = $this->toDto($item, $fieldMap);

            if ($dto !== null) {
                yield $dto;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMap
     */
    private function toDto(array $item, array $fieldMap): ?RawEventDTO
    {
        $startsAt = $this->parseDate(Arr::get($item, $fieldMap['starts_at']));

        if ($startsAt === null) {
            return null;
        }

        return new RawEventDTO(
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
