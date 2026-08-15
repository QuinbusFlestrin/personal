<?php

namespace Tests\Feature\Services\Import;

use App\Models\Category;
use App\Models\Event;
use App\Models\ImportRun;
use App\Models\Source;
use App\Services\Import\ImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Event-side import concerns: the robots.txt gate on page-fetching connectors,
 * API keys carried in query parameters, and per-source category vocabulary.
 */
class EventImportSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_disallowed_page_is_not_fetched_and_the_reason_is_recorded(): void
    {
        Http::fake([
            'venue.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /agenda"),
            'venue.example.org/agenda' => Http::response('<html></html>'),
        ]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_LD,
            'config' => ['url' => 'https://venue.example.org/agenda'],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(ImportRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('robots.txt disallows', $run->error_log[0]['message']);

        // The page itself must never have been requested.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/agenda'));
    }

    public function test_a_permitted_page_is_fetched_with_an_identifying_user_agent(): void
    {
        Http::fake([
            'venue.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /admin"),
            'venue.example.org/agenda' => Http::response(<<<'HTML'
            <html><head><script type="application/ld+json">
            {"@type":"Event","name":"Concert","startDate":"2026-09-01T20:00:00+02:00"}
            </script></head><body></body></html>
            HTML),
        ]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_LD,
            'config' => ['url' => 'https://venue.example.org/agenda'],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(1, $run->items_created);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/agenda')
            && str_contains($request->header('User-Agent')[0] ?? '', 'SwissEventsBot'));
    }

    public function test_a_site_we_control_can_opt_out_of_the_robots_gate(): void
    {
        Http::fake([
            'venue.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /"),
            'venue.example.org/agenda' => Http::response(<<<'HTML'
            <html><head><script type="application/ld+json">
            {"@type":"Event","name":"Concert","startDate":"2026-09-01T20:00:00+02:00"}
            </script></head><body></body></html>
            HTML),
        ]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_LD,
            'config' => ['url' => 'https://venue.example.org/agenda', 'ignore_robots' => true],
        ]);

        $this->assertSame(1, app(ImportRunner::class)->run($source)->items_created);
    }

    /**
     * Ticketmaster's Discovery API takes its key as a query parameter, so the
     * secret indirection has to cover query values as well as headers —
     * otherwise the key would have to live in the database.
     */
    public function test_an_api_key_in_a_query_parameter_resolves_from_config(): void
    {
        config(['services.sources.secrets.ticketmaster' => 'tm-secret-key']);

        Http::fake(['app.ticketmaster.com/*' => Http::response(['_embedded' => ['events' => []]])]);

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://app.ticketmaster.com/discovery/v2/events.json',
                'items_path' => '_embedded.events',
                'query' => ['apikey' => 'secret:ticketmaster', 'countryCode' => 'CH'],
                'field_map' => ['title' => 'name', 'starts_at' => 'dates.start.dateTime'],
            ],
        ]);

        app(ImportRunner::class)->run($source);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'apikey=tm-secret-key'));
    }

    public function test_a_configured_secret_never_reaches_the_import_error_log(): void
    {
        config(['services.sources.secrets.ticketmaster' => 'tm-secret-key']);

        // A connection-level failure is the case that can echo the full URL,
        // key and all, into the message we persist.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException(
            'cURL error 6: Could not resolve host for https://app.ticketmaster.com/discovery/v2/events.json?apikey=tm-secret-key'
        ));

        $source = Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://app.ticketmaster.com/discovery/v2/events.json',
                'query' => ['apikey' => 'secret:ticketmaster'],
                'field_map' => ['title' => 'name', 'starts_at' => 'dates.start.dateTime'],
            ],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $message = $run->error_log[0]['message'];
        $this->assertStringNotContainsString('tm-secret-key', $message);
        $this->assertStringContainsString('[redacted]', $message);
    }

    public function test_a_source_can_map_its_own_category_vocabulary_onto_ours(): void
    {
        $concerts = Category::create(['name' => 'Concerts', 'slug' => 'concerts', 'sort_order' => 0]);

        Http::fake(['api.example.org/*' => Http::response(['events' => [[
            'id' => 'tm-1',
            'name' => 'Big Gig',
            'dates' => ['start' => ['dateTime' => '2026-09-01T20:00:00Z']],
            'classifications' => [['segment' => ['name' => 'Music']]],
        ]]])]);

        $source = Source::factory()->trusted()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'events',
                'field_map' => [
                    'external_id' => 'id',
                    'title' => 'name',
                    'starts_at' => 'dates.start.dateTime',
                    'category_hint' => 'classifications.0.segment.name',
                ],
                'category_map' => ['Music' => 'Concerts'],
            ],
        ]);

        app(ImportRunner::class)->run($source);

        $this->assertSame($concerts->id, Event::first()->category_id);
    }

    /**
     * Page-mode pagination had no coverage until now — Ticketmaster is the
     * first source to use it, and its pages start at 0 rather than 1.
     */
    public function test_page_mode_pagination_walks_pages_from_zero(): void
    {
        Http::fake(['api.example.org/*' => Http::sequence()
            ->push(['events' => [['id' => 'a', 'name' => 'One', 'dates' => ['start' => ['dateTime' => '2026-09-01T20:00:00Z']]]]])
            ->push(['events' => [['id' => 'b', 'name' => 'Two', 'dates' => ['start' => ['dateTime' => '2026-09-02T20:00:00Z']]]]])
            ->push(['events' => []]),
        ]);

        $source = Source::factory()->trusted()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'events',
                'pagination' => [
                    'mode' => 'page', 'param' => 'page', 'size_param' => 'size',
                    'page_size' => 200, 'start' => 0, 'max_pages' => 5,
                ],
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'dates.start.dateTime'],
            ],
        ]);

        $run = app(ImportRunner::class)->run($source);

        $this->assertSame(2, $run->items_created);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=0'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=1'));
    }
}
