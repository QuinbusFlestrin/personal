<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use App\Services\Import\Support\RobotsGate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Static-HTML scraper only — no headless browser, since shared hosting can't
 * run one. Selectors live in the source's `config`, so a new scrape target
 * (of this same static-HTML shape) is a data change, not a new class.
 *
 * Expected config shape:
 *   url: string
 *   list_selector: string (CSS selector matching each event block)
 *   fields: {
 *     title: { selector, attr? }
 *     starts_at: { selector, attr? }
 *     description: { selector, attr? }
 *     external_url: { selector, attr: "href" }
 *     image: { selector, attr: "src" }
 *   }
 */
class ScraperConnector implements SourceConnector
{
    public function __construct(private readonly RobotsGate $robots) {}

    public function fetch(Source $source): iterable
    {
        $config = $source->config ?? [];
        $url = $config['url'] ?? null;
        $listSelector = $config['list_selector'] ?? null;
        $fields = $config['fields'] ?? [];

        if (! $url || ! $listSelector || ! isset($fields['title'], $fields['starts_at'])) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) config must set url, list_selector, and fields.title/starts_at");
        }

        $userAgent = $config['user_agent'] ?? RobotsGate::USER_AGENT.' (+https://events.mrminimalista.ch)';

        if (! ($config['ignore_robots'] ?? false) && ! $this->robots->allows($url, $userAgent)) {
            throw new RuntimeException(
                "robots.txt disallows fetching {$url} for [{$userAgent}]. ".
                'Set config.ignore_robots to true only for a site you control.'
            );
        }

        $response = Http::timeout(20)->withHeaders(['User-Agent' => $userAgent])->get($url);
        $response->throw();

        $crawler = new Crawler($response->body(), $url);

        foreach ($crawler->filter($listSelector) as $node) {
            $nodeCrawler = new Crawler($node);
            $startsAt = $this->parseDate($this->extract($nodeCrawler, $fields['starts_at'] ?? null));

            if ($startsAt === null) {
                continue;
            }

            yield new RawEventDTO(
                externalId: null,
                title: (string) $this->extract($nodeCrawler, $fields['title']),
                description: $this->extract($nodeCrawler, $fields['description'] ?? null),
                startsAt: $startsAt,
                endsAt: null,
                venueName: $this->extract($nodeCrawler, $fields['venue_name'] ?? null),
                externalUrl: $this->extract($nodeCrawler, $fields['external_url'] ?? null),
                image: $this->extract($nodeCrawler, $fields['image'] ?? null),
            );
        }
    }

    /**
     * @param  array{selector: string, attr?: string}|null  $field
     */
    private function extract(Crawler $node, ?array $field): ?string
    {
        if ($field === null) {
            return null;
        }

        $matches = $node->filter($field['selector']);

        if ($matches->count() === 0) {
            return null;
        }

        $value = isset($field['attr'])
            ? $matches->attr($field['attr'])
            : $matches->text(null, true);

        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
