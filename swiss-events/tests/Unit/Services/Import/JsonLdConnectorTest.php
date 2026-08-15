<?php

namespace Tests\Unit\Services\Import;

use App\Models\Source;
use App\Services\Import\Connectors\JsonLdConnector;
use App\Services\Import\DTO\RawEventDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JsonLdConnectorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<RawEventDTO>
     */
    private function fetch(string $html, array $config = []): array
    {
        Http::fake(['venue.example.org/*' => Http::response($html)]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_LD,
            'config' => $config + ['url' => 'https://venue.example.org/agenda'],
        ]);

        // Keys restart per inner generator, so preserve_keys must be false.
        return iterator_to_array(app(JsonLdConnector::class)->fetch($source), false);
    }

    private function page(string $json): string
    {
        return <<<HTML
        <html><head>
        <script type="application/ld+json">{$json}</script>
        </head><body></body></html>
        HTML;
    }

    public function test_it_maps_a_single_event_object(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        {
          "@context": "https://schema.org",
          "@type": "MusicEvent",
          "@id": "https://venue.example.org/events/1",
          "name": "Jazz Night",
          "description": "<p>An <b>evening</b> of jazz</p>",
          "startDate": "2026-09-01T20:00:00+02:00",
          "endDate": "2026-09-01T23:00:00+02:00",
          "url": "https://venue.example.org/events/1",
          "image": "https://venue.example.org/img/1.jpg",
          "location": {
            "@type": "Place",
            "name": "Kaufleuten",
            "address": {
              "@type": "PostalAddress",
              "streetAddress": "Pelikanplatz 18",
              "postalCode": "8001",
              "addressLocality": "Zürich"
            }
          },
          "offers": {"@type": "Offer", "price": "35", "priceCurrency": "CHF"}
        }
        JSON));

        $this->assertCount(1, $events);
        $event = $events[0];

        $this->assertSame('Jazz Night', $event->title);
        $this->assertSame('https://venue.example.org/events/1', $event->externalId);
        // The fixture says 20:00+02:00, i.e. 18:00 UTC — the offset must be honoured, not dropped.
        $this->assertSame('2026-09-01 18:00:00', $event->startsAt->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($event->endsAt);
        $this->assertSame('Kaufleuten', $event->venueName);
        $this->assertSame('Pelikanplatz 18, 8001, Zürich', $event->venueAddress);
        $this->assertSame('https://venue.example.org/img/1.jpg', $event->image);
        $this->assertSame('35 CHF', $event->priceInfo);
        // MusicEvent maps onto the seeded taxonomy.
        $this->assertSame('Concerts', $event->categoryHint);
        // Markup is stripped — the public views render this as plain text.
        $this->assertSame('An evening of jazz', $event->description);
    }

    public function test_it_finds_events_inside_a_graph_envelope(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        {
          "@context": "https://schema.org",
          "@graph": [
            {"@type": "Organization", "name": "Some Venue"},
            {"@type": "Event", "name": "Talk", "startDate": "2026-09-02T18:00:00Z"},
            {"@type": "TheaterEvent", "name": "Hamlet", "startDate": "2026-09-03T19:30:00Z"}
          ]
        }
        JSON));

        $this->assertCount(2, $events);
        $this->assertSame('Talk', $events[0]->title);
        $this->assertSame('Hamlet', $events[1]->title);
        $this->assertSame('Theatre & Shows', $events[1]->categoryHint);
    }

    public function test_it_finds_events_in_a_bare_list_and_nested_item_lists(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        [
          {"@type": "Event", "name": "First", "startDate": "2026-09-01T10:00:00Z"},
          {
            "@type": "ItemList",
            "itemListElement": [
              {"@type": "ListItem", "item": {"@type": "Festival", "name": "Second", "startDate": "2026-09-04T10:00:00Z"}}
            ]
          }
        ]
        JSON));

        $this->assertSame(['First', 'Second'], array_map(fn ($e) => $e->title, $events));
        $this->assertSame('Festivals', $events[1]->categoryHint);
    }

    public function test_it_skips_events_without_a_title_or_start_date(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        [
          {"@type": "Event", "name": "No date"},
          {"@type": "Event", "startDate": "2026-09-01T10:00:00Z"},
          {"@type": "Event", "name": "Complete", "startDate": "2026-09-01T10:00:00Z"}
        ]
        JSON));

        $this->assertCount(1, $events);
        $this->assertSame('Complete', $events[0]->title);
    }

    public function test_a_malformed_block_does_not_discard_the_valid_blocks(): void
    {
        $html = <<<'HTML'
        <html><head>
        <script type="application/ld+json">{ this is not json }</script>
        <script type="application/ld+json">{"@type": "Event", "name": "Survivor", "startDate": "2026-09-01T10:00:00Z"}</script>
        </head><body></body></html>
        HTML;

        $events = $this->fetch($html);

        $this->assertCount(1, $events);
        $this->assertSame('Survivor', $events[0]->title);
    }

    public function test_it_handles_language_tagged_values_and_list_wrapped_fields(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        {
          "@type": "Event",
          "name": {"@value": "Fête de la Musique", "@language": "fr"},
          "startDate": "2026-06-21T18:00:00+02:00",
          "location": [{"@type": "Place", "name": "Parc des Bastions"}],
          "image": [{"@type": "ImageObject", "url": "https://venue.example.org/img/f.jpg"}]
        }
        JSON));

        $this->assertCount(1, $events);
        $this->assertSame('Fête de la Musique', $events[0]->title);
        $this->assertSame('Parc des Bastions', $events[0]->venueName);
        $this->assertSame('https://venue.example.org/img/f.jpg', $events[0]->image);
    }

    public function test_it_falls_back_to_the_page_url_when_the_event_has_none(): void
    {
        $events = $this->fetch($this->page(<<<'JSON'
        {"@type": "Event", "name": "No link", "startDate": "2026-09-01T10:00:00Z"}
        JSON));

        $this->assertSame('https://venue.example.org/agenda', $events[0]->externalUrl);
        $this->assertNull($events[0]->externalId);
    }
}
