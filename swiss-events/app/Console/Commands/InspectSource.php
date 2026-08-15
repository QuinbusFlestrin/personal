<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Import\Connectors\JsonLdConnector;
use App\Services\Import\Support\RobotsGate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

#[Signature('sources:inspect {url : Page to examine, e.g. a venue\'s agenda page}')]
#[Description('Report which connector type a site can be imported with (feed, JSON-LD, JSON endpoint or selectors)')]
class InspectSource extends Command
{
    /**
     * Onboarding a new site otherwise means opening devtools and guessing.
     * This walks the same ladder we prefer — official feed, then embedded
     * schema.org markup, then an underlying JSON endpoint, then selectors —
     * and reports the cheapest rung that actually applies.
     */
    public function handle(JsonLdConnector $jsonLd, RobotsGate $robots): int
    {
        $url = $this->argument('url');

        // Report this first: if the site disallows us, the rest is moot and
        // nobody should spend time configuring a source that will never run.
        $allowed = $robots->allows($url);
        $this->line($allowed
            ? '<info>robots.txt: crawlable</info>'
            : '<comment>robots.txt: DISALLOWED for our crawler — this source cannot be imported by scraping</comment>');
        $this->line('');

        try {
            $response = Http::timeout(20)
                // Same identity the importer uses, so what we see here is what
                // an actual run would see.
                ->withHeaders(['User-Agent' => RobotsGate::USER_AGENT.' (+https://events.mrminimalista.ch)'])
                ->get($url);
            $response->throw();
        } catch (Throwable $e) {
            $this->error("Could not fetch {$url}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $body = $response->body();
        $crawler = new Crawler($body, $url);

        $feeds = $this->feeds($crawler);
        $calendars = $this->calendars($crawler);
        $events = $this->jsonLdEvents($crawler, $jsonLd);
        $endpoints = $this->jsonEndpoints($body);

        $this->report('iCal calendars', $calendars);
        $this->report('RSS/Atom feeds', $feeds);
        $this->line('');

        if ($events !== []) {
            $this->info('JSON-LD: found '.count($events).' schema.org Event object(s).');
            $sample = $events[0];
            $this->line('  sample: '.json_encode([
                'name' => $sample['name'] ?? null,
                'startDate' => $sample['startDate'] ?? null,
                'location' => $sample['location'] ?? null,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('JSON-LD: no schema.org Event objects found.');
        }

        $this->line('');
        $this->report('Possible JSON endpoints', $endpoints);

        $this->line('');
        $this->recommend($calendars, $feeds, $events, $endpoints);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $values
     */
    private function report(string $label, array $values): void
    {
        if ($values === []) {
            $this->line("{$label}: none.");

            return;
        }

        $this->info("{$label}: ".count($values));

        foreach (array_slice($values, 0, 5) as $value) {
            $this->line("  {$value}");
        }
    }

    /**
     * @return list<string>
     */
    private function feeds(Crawler $crawler): array
    {
        return $this->hrefs(
            $crawler->filter('link[rel="alternate"][type*="rss"], link[rel="alternate"][type*="atom"]')
        );
    }

    /**
     * @return list<string>
     */
    private function calendars(Crawler $crawler): array
    {
        return array_values(array_unique([
            ...$this->hrefs($crawler->filter('link[type*="calendar"]')),
            ...array_filter(
                $this->hrefs($crawler->filter('a')),
                fn (string $href) => str_contains(strtolower($href), '.ics'),
            ),
        ]));
    }

    /**
     * @return list<string>
     */
    private function hrefs(Crawler $nodes): array
    {
        return array_values(array_filter($nodes->each(
            fn (Crawler $node) => $node->attr('href'),
        )));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdEvents(Crawler $crawler, JsonLdConnector $jsonLd): array
    {
        $events = [];

        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $decoded = json_decode($node->textContent, true);

            if (is_array($decoded)) {
                $events = [...$events, ...$jsonLd->extractEvents($decoded)];
            }
        }

        return $events;
    }

    /**
     * Heuristic only — surfaces candidate URLs the page's own scripts call, so
     * they can be opened and checked by hand. A real endpoint here usually
     * means json_api beats scraping.
     *
     * @return list<string>
     */
    private function jsonEndpoints(string $body): array
    {
        preg_match_all('~["\'](https?://[^"\']+|/[^"\']*)(?:/api/|/api\?|\.json)([^"\']*)["\']~i', $body, $matches);

        $candidates = array_map(
            fn (int $i) => $matches[1][$i].$matches[2][$i],
            array_keys($matches[1] ?? []),
        );

        return array_slice(array_values(array_unique($candidates)), 0, 10);
    }

    /**
     * @param  list<string>  $calendars
     * @param  list<string>  $feeds
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $endpoints
     */
    private function recommend(array $calendars, array $feeds, array $events, array $endpoints): void
    {
        [$type, $why] = match (true) {
            $calendars !== [] => [Source::TYPE_ICAL, 'an iCal feed is published — the most stable option available'],
            $feeds !== [] => [Source::TYPE_RSS, 'an RSS/Atom feed is published'],
            $events !== [] => [Source::TYPE_JSON_LD, 'the page embeds schema.org Event markup — no selectors needed'],
            $endpoints !== [] => [Source::TYPE_JSON_API, 'candidate JSON endpoints found — open one and confirm it returns events'],
            default => [Source::TYPE_SCRAPER, 'no feed, markup or endpoint found — CSS selectors, or skip the site'],
        };

        $this->info("Recommended source type: {$type}");
        $this->line("  because {$why}.");
    }
}
