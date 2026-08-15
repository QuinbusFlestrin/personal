<?php

namespace Tests\Feature\Services\Import;

use App\Models\Canton;
use App\Models\City;
use App\Models\Event;
use App\Models\ImportRun;
use App\Models\Source;
use App\Services\Import\Dedup\EventDeduplicator;
use App\Services\Import\ImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_source_publishes_events_immediately(): void
    {
        Http::fake(['api.example.org/*' => Http::response([
            'items' => [
                ['id' => 'ext-1', 'name' => 'Jazz Night', 'start' => '2026-09-01T20:00:00Z', 'loc' => 'Kaufleuten'],
            ],
        ])]);

        $source = Source::factory()->trusted()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start', 'venue_name' => 'loc'],
            ],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(ImportRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->items_created);
        $this->assertSame(Event::STATUS_PUBLISHED, Event::first()->status);
    }

    public function test_untrusted_source_queues_events_for_review(): void
    {
        Http::fake(['api.example.org/*' => Http::response([
            'items' => [['id' => 'ext-1', 'name' => 'Open Mic', 'start' => '2026-09-01T20:00:00Z']],
        ])]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start'],
            ],
        ]);

        app(ImportRunner::class)->run($source);

        $this->assertSame(Event::STATUS_PENDING_REVIEW, Event::first()->status);
    }

    public function test_reimporting_the_same_external_id_updates_instead_of_duplicating(): void
    {
        Http::fake(['api.example.org/*' => Http::response([
            'items' => [['id' => 'ext-1', 'name' => 'Jazz Night', 'start' => '2026-09-01T20:00:00Z']],
        ])]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start'],
            ],
        ]);

        $runner = app(ImportRunner::class);
        $first = $runner->run($source);
        $second = $runner->run($source);

        $this->assertSame(1, $first->items_created);
        $this->assertSame(1, $second->items_updated);
        $this->assertSame(0, $second->items_created);
        $this->assertSame(1, Event::count());
    }

    public function test_cross_source_duplicates_are_skipped(): void
    {
        Http::fake([
            'api-a.example.org/*' => Http::response([
                'items' => [['id' => 'a-1', 'name' => 'Jazz Night', 'start' => '2026-09-01T20:00:00Z', 'loc' => 'Kaufleuten']],
            ]),
            'api-b.example.org/*' => Http::response([
                // Same event content, no external id, from a different source.
                'items' => [['name' => 'Jazz Night', 'start' => '2026-09-01T20:00:00Z', 'loc' => 'Kaufleuten']],
            ]),
        ]);

        $fieldMapA = ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start', 'venue_name' => 'loc'];
        $fieldMapB = ['title' => 'name', 'starts_at' => 'start', 'venue_name' => 'loc'];

        $sourceA = Source::factory()->create(['config' => ['url' => 'https://api-a.example.org/events', 'items_path' => 'items', 'field_map' => $fieldMapA]]);
        $sourceB = Source::factory()->create(['config' => ['url' => 'https://api-b.example.org/events', 'items_path' => 'items', 'field_map' => $fieldMapB]]);

        $runner = app(ImportRunner::class);
        $runner->run($sourceA);
        $runB = $runner->run($sourceB);

        $this->assertSame(1, $runB->items_skipped_duplicate);
        $this->assertSame(1, Event::count());
    }

    public function test_rss_connector_parses_feed_items(): void
    {
        $rss = '<?xml version="1.0"?><rss><channel><item>'
            .'<title>Open Air Cinema</title><link>https://x.example/e</link><guid>guid-1</guid>'
            .'<pubDate>Mon, 10 Aug 2026 19:00:00 GMT</pubDate><description>Great show</description>'
            .'</item></channel></rss>';

        Http::fake(['x.example/feed.xml' => Http::response($rss)]);

        $source = Source::factory()->trusted()->create([
            'type' => Source::TYPE_RSS,
            'config' => ['url' => 'https://x.example/feed.xml'],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(1, $run->items_created);
        $this->assertSame('Open Air Cinema', Event::first()->title);
    }

    public function test_ical_connector_parses_vevent_blocks(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:ics-1\r\nDTSTART:20260901T180000Z\r\n"
            ."DTEND:20260901T210000Z\r\nSUMMARY:Open Air Cinema\r\nLOCATION:Parc des Bastions\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR";

        Http::fake(['y.example/cal.ics' => Http::response($ics)]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_ICAL,
            'config' => ['url' => 'https://y.example/cal.ics'],
        ]);

        $run = app(ImportRunner::class)->run($source);
        $event = Event::first();

        $this->assertSame(1, $run->items_created);
        $this->assertSame('Parc des Bastions', $event->venue->name);
        $this->assertSame('2026-09-01 18:00:00', $event->starts_at->toDateTimeString());
    }

    public function test_a_broken_source_fails_gracefully_without_throwing(): void
    {
        $source = Source::factory()->create(['type' => Source::TYPE_JSON_API, 'config' => []]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(ImportRun::STATUS_FAILED, $run->status);
        $this->assertNotEmpty($run->error_log);
        $this->assertSame(ImportRun::STATUS_FAILED, $source->fresh()->last_run_status);
    }

    public function test_one_bad_item_does_not_abort_the_rest_of_the_batch(): void
    {
        Http::fake(['api.example.org/*' => Http::response([
            'items' => [
                ['id' => 'ext-1', 'name' => 'Good Event A', 'start' => '2026-09-01T20:00:00Z'],
                ['id' => 'ext-2', 'name' => 'Good Event B', 'start' => '2026-09-02T20:00:00Z'],
            ],
        ])]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start'],
            ],
        ]);

        // Force the dedup hashing step (used for every item) to blow up on
        // the second call only, simulating an unexpected runtime failure
        // (e.g. a transient DB error) partway through a batch.
        $this->partialMock(EventDeduplicator::class, function ($mock) {
            $mock->shouldReceive('hash')
                ->once()
                ->andReturn('hash-a');
            $mock->shouldReceive('hash')
                ->once()
                ->andThrow(new \RuntimeException('simulated failure'));
            $mock->shouldReceive('findDuplicate')->andReturn(null);
        });

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(1, $run->items_created);
        $this->assertSame(1, $run->items_failed);
        $this->assertSame(ImportRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, Event::count());
    }

    /**
     * Canton is the main filter on /events and is denormalized onto the event
     * from its venue, so an import that can't place its venues produces events
     * that browsing will never surface.
     */
    public function test_it_places_imported_events_in_a_canton(): void
    {
        $canton = Canton::create(['code' => 'ZH', 'name' => 'Zürich', 'slug' => 'zurich']);
        City::create(['name' => 'Zürich', 'slug' => 'zurich', 'canton_id' => $canton->id]);

        Http::fake(['api.example.org/*' => Http::response([
            'items' => [[
                'id' => 'ext-1',
                'name' => 'Jazz Night',
                'start' => '2026-09-01T20:00:00Z',
                'loc' => 'Kaufleuten',
                'addr' => 'Pelikanplatz 18, 8001 Zürich',
            ]],
        ])]);

        $source = Source::factory()->trusted()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => [
                    'external_id' => 'id', 'title' => 'name', 'starts_at' => 'start',
                    'venue_name' => 'loc', 'venue_address' => 'addr',
                ],
            ],
        ]);

        app(ImportRunner::class)->run($source);

        $event = Event::first();
        $this->assertSame($canton->id, $event->canton_id);
        $this->assertSame($canton->id, $event->venue->canton_id);
    }
}
