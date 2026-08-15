<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\DTO\RawEventDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Reads schema.org Event objects out of <script type="application/ld+json">
 * blocks embedded in ordinary HTML pages.
 *
 * This is the highest-yield connector for sites with no feed or API: search
 * engines reward Event markup, so a large share of venue and agenda pages
 * already publish exactly the structured fields we need. Unlike ScraperConnector
 * it needs no per-site CSS selectors, and the markup usually survives redesigns
 * that would break selectors. Like the scraper it fetches static HTML only — no
 * headless browser, which shared hosting cannot run.
 *
 * Expected config shape:
 *   url: string             (a page to read)
 *   urls: string[]          (or several — e.g. one listing page per month)
 *   user_agent: string|null (some sites reject the default client)
 */
class JsonLdConnector implements SourceConnector
{
    /**
     * schema.org Event subtypes that map cleanly onto our seeded taxonomy.
     * Anything unmapped yields a null hint, which ImportRunner treats as
     * "uncategorised" rather than guessing wrong.
     */
    private const TYPE_TO_CATEGORY = [
        'MusicEvent' => 'Concerts',
        'Festival' => 'Festivals',
        'TheaterEvent' => 'Theatre & Shows',
        'ComedyEvent' => 'Theatre & Shows',
        'DanceEvent' => 'Theatre & Shows',
        'ScreeningEvent' => 'Theatre & Shows',
        'ExhibitionEvent' => 'Exhibitions',
        'VisualArtsEvent' => 'Exhibitions',
        'ChildrensEvent' => 'Family & Kids',
        'SportsEvent' => 'Sports',
        'SaleEvent' => 'Markets & Fairs',
    ];

    /**
     * Event subtypes whose names don't end in "Event".
     */
    private const EXTRA_EVENT_TYPES = ['Festival', 'Hackathon', 'CourseInstance'];

    public function fetch(Source $source): iterable
    {
        $config = $source->config ?? [];
        $urls = $this->urls($config);

        if ($urls === []) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) config must set url or urls");
        }

        foreach ($urls as $url) {
            yield from $this->fetchUrl($url, $config);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function urls(array $config): array
    {
        $urls = $config['urls'] ?? $config['url'] ?? [];

        return array_values(array_filter(
            is_array($urls) ? $urls : [$urls],
            fn ($url) => is_string($url) && $url !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return iterable<RawEventDTO>
     */
    private function fetchUrl(string $url, array $config): iterable
    {
        $request = Http::timeout(20);

        if (isset($config['user_agent'])) {
            $request = $request->withHeaders(['User-Agent' => $config['user_agent']]);
        }

        $response = $request->get($url);
        $response->throw();

        $crawler = new Crawler($response->body(), $url);

        foreach ($crawler->filter('script[type="application/ld+json"]') as $node) {
            $decoded = json_decode($node->textContent, true);

            // One malformed block shouldn't cost us the other blocks on the
            // page — sites frequently ship a broken script alongside good ones.
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->extractEvents($decoded) as $event) {
                $dto = $this->toDto($event, $url);

                if ($dto !== null) {
                    yield $dto;
                }
            }
        }
    }

    /**
     * A block may be a single object, a bare list, or a @graph envelope, and
     * events also turn up nested inside ItemList wrappers. Walk all shapes
     * uniformly instead of special-casing each one.
     *
     * Public so `sources:inspect` can report what a page offers without
     * duplicating this traversal.
     *
     * @param  array<mixed>  $node
     * @return list<array<string, mixed>>
     */
    public function extractEvents(array $node): array
    {
        if ($this->isEvent($node)) {
            return [$node];
        }

        $events = [];

        foreach ($node as $value) {
            if (is_array($value)) {
                $events = [...$events, ...$this->extractEvents($value)];
            }
        }

        return $events;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function isEvent(array $node): bool
    {
        foreach ((array) ($node['@type'] ?? []) as $type) {
            if (! is_string($type)) {
                continue;
            }

            if (str_ends_with($type, 'Event') || in_array($type, self::EXTRA_EVENT_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function toDto(array $event, string $pageUrl): ?RawEventDTO
    {
        $title = $this->text($event['name'] ?? null);
        $startsAt = $this->parseDate($event['startDate'] ?? null);

        // schema.org permits partial markup; without a title and a start date
        // there is no usable event here.
        if ($title === null || $startsAt === null) {
            return null;
        }

        $location = $this->first($event['location'] ?? null);

        return new RawEventDTO(
            externalId: $this->text($event['@id'] ?? null) ?? $this->text($event['url'] ?? null),
            title: $title,
            description: $this->description($event['description'] ?? null),
            startsAt: $startsAt,
            endsAt: $this->parseDate($event['endDate'] ?? null),
            venueName: is_array($location) ? $this->text($location['name'] ?? null) : $this->text($location),
            venueAddress: is_array($location) ? $this->address($location['address'] ?? null) : null,
            categoryHint: $this->categoryHint($event['@type'] ?? null),
            externalUrl: $this->text($event['url'] ?? null) ?? $pageUrl,
            image: $this->image($event['image'] ?? null),
            priceInfo: $this->price($event['offers'] ?? null),
        );
    }

    private function categoryHint(mixed $type): ?string
    {
        foreach ((array) $type as $candidate) {
            if (is_string($candidate) && isset(self::TYPE_TO_CATEGORY[$candidate])) {
                return self::TYPE_TO_CATEGORY[$candidate];
            }
        }

        return null;
    }

    /**
     * JSON-LD values are routinely wrapped in a single-element list.
     */
    private function first(mixed $value): mixed
    {
        if (is_array($value) && array_is_list($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }

    private function text(mixed $value): ?string
    {
        $value = $this->first($value);

        // Language-tagged values arrive as {"@value": "...", "@language": "de"}.
        if (is_array($value)) {
            $value = $value['@value'] ?? null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function description(mixed $value): ?string
    {
        $text = $this->text($value);

        // Descriptions often carry escaped markup; the public views render
        // this as plain text, so strip rather than pass HTML through.
        return $text !== null ? trim(html_entity_decode(strip_tags($text))) : null;
    }

    private function address(mixed $address): ?string
    {
        if (is_string($address)) {
            return $this->text($address);
        }

        $address = $this->first($address);

        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            $this->text($address['streetAddress'] ?? null),
            $this->text($address['postalCode'] ?? null),
            $this->text($address['addressLocality'] ?? null),
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function image(mixed $image): ?string
    {
        $image = $this->first($image);

        // Either a bare URL string or an ImageObject.
        if (is_array($image)) {
            $image = $image['url'] ?? $image['contentUrl'] ?? null;
        }

        return $this->text($image);
    }

    private function price(mixed $offers): ?string
    {
        $offer = $this->first($offers);

        if (! is_array($offer)) {
            return null;
        }

        $price = $this->text($offer['price'] ?? null)
            ?? $this->text($offer['lowPrice'] ?? null);

        if ($price === null) {
            return null;
        }

        $currency = $this->text($offer['priceCurrency'] ?? null);

        return $currency !== null ? "{$price} {$currency}" : $price;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = $this->text($value);

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
