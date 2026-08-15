<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
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

        $pagination = $config['pagination'] ?? [];
        $mode = $pagination['mode'] ?? null;
        $maxPages = max(1, (int) ($pagination['max_pages'] ?? 1));
        $cursor = (int) ($pagination['start'] ?? ($mode === 'offset' ? 0 : 1));

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->request($config, $source)->get($url, $this->query($config, $mode, $cursor));
            $response->throw();

            $body = $response->json();
            $items = isset($config['items_path']) ? Arr::get($body, $config['items_path'], []) : $body;

            if (! is_array($items) || $items === []) {
                return;
            }

            foreach ($items as $item) {
                $dto = $this->toDto($item, $fieldMap);

                if ($dto !== null) {
                    yield $dto;
                }
            }

            if ($mode === null) {
                return;
            }

            if ($mode === 'link') {
                $next = Arr::get($body, $pagination['next_path'] ?? 'next');

                if (! is_string($next) || $next === '') {
                    return;
                }

                // The next-page URL already carries its own query string.
                $url = $next;

                continue;
            }

            $cursor += $mode === 'offset'
                ? (int) ($pagination['page_size'] ?? count($items))
                : 1;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function request(array $config, Source $source): PendingRequest
    {
        $request = Http::timeout(20)->acceptJson();

        $headers = $this->resolveSecrets($config['headers'] ?? [], $source);

        if (isset($config['user_agent'])) {
            $headers['User-Agent'] = $config['user_agent'];
        }

        return $headers !== [] ? $request->withHeaders($headers) : $request;
    }

    /**
     * Config JSON is editable in the admin UI, so API keys are referenced by
     * name ("secret:myswitzerland") and resolved from config/services.php,
     * never stored in the database.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function resolveSecrets(array $values, Source $source): array
    {
        foreach ($values as $key => $value) {
            if (! is_string($value) || ! str_starts_with($value, 'secret:')) {
                continue;
            }

            $name = substr($value, strlen('secret:'));
            $secret = config("services.sources.secrets.{$name}");

            if (blank($secret)) {
                throw new RuntimeException(
                    "Source #{$source->id} ({$source->name}) references secret [{$name}], but services.sources.secrets.{$name} is empty. ".
                    'Add it to config/services.php and set the variable in the server .env.'
                );
            }

            $values[$key] = $secret;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function query(array $config, ?string $mode, int $cursor): array
    {
        $query = $config['query'] ?? [];
        $pagination = $config['pagination'] ?? [];

        if ($mode === 'page' || $mode === 'offset') {
            $query[$pagination['param'] ?? 'page'] = $cursor;

            if (isset($pagination['size_param'], $pagination['page_size'])) {
                $query[$pagination['size_param']] = $pagination['page_size'];
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMap
     */
    private function toDto(mixed $item, array $fieldMap): ?RawEventDTO
    {
        if (! is_array($item)) {
            return null;
        }

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
