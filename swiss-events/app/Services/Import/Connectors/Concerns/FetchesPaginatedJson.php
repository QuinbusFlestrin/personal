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
        $followedUrl = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->request($config, $source)->get(
                $url,
                // Some APIs take their key as a query parameter rather than a
                // header (Ticketmaster's `apikey`), so secrets resolve here too.
                $this->resolveSecrets($this->query($config, $mode, $cursor, $followedUrl), $source)
            );
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

                // A next link pointing back at the page just fetched would
                // otherwise spend the whole max_pages budget re-importing it.
                if (! is_string($next) || $next === '' || $next === $url) {
                    return;
                }

                $url = $next;
                $followedUrl = $next;

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
        // A single blip midway through pagination would otherwise discard the
        // remaining pages, so one cheap retry before giving up.
        $request = Http::timeout(20)->retry(2, 300, throw: false)->acceptJson();

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
     * @param  string|null  $followedUrl  Set when the current URL came from a
     *                                    next link, whose own parameters must survive.
     * @return array<string, mixed>
     */
    private function query(array $config, ?string $mode, int $cursor, ?string $followedUrl = null): array
    {
        $query = $config['query'] ?? [];
        $pagination = $config['pagination'] ?? [];

        // Guzzle *replaces* a URI's query string with whatever is passed here
        // (and Laravel's get() sets that option for any second argument, even
        // an empty array). So a next link's parameters — its page number above
        // all — have to be parsed out and merged back in, or every page after
        // the first silently repeats the first.
        if ($followedUrl !== null) {
            parse_str((string) parse_url($followedUrl, PHP_URL_QUERY), $ownParameters);

            // The link's own values win; config query fills in anything the
            // API omitted from the link.
            return array_merge($query, $ownParameters);
        }

        if ($mode === 'page' || $mode === 'offset') {
            $query[$pagination['param'] ?? 'page'] = $cursor;

            if (isset($pagination['size_param'], $pagination['page_size'])) {
                $query[$pagination['size_param']] = $pagination['page_size'];
            }
        }

        return $query;
    }
}
