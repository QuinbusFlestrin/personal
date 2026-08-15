<?php

namespace App\Services\Import\Connectors\Concerns;

use App\Models\Source;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Shared HTTP plumbing for JSON sources: authentication, fixed query params and
 * pagination. Kept separate from field mapping so the event and place
 * connectors differ only in what they build out of each item, not in how the
 * items are retrieved.
 *
 * Config keys consumed here:
 *   url, items_path, headers, query, user_agent, pagination
 * See JsonApiConnector's docblock for the full shape.
 */
trait FetchesPaginatedJson
{
    /**
     * Yields raw decoded items across every page, stopping at the first empty
     * page or at pagination.max_pages — whichever comes first.
     *
     * @return iterable<array<string, mixed>>
     */
    protected function jsonItems(Source $source): iterable
    {
        $config = $source->config ?? [];
        $url = $config['url'] ?? null;

        if (! $url) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) is missing config.url");
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
                if (is_array($item)) {
                    yield $item;
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
}
