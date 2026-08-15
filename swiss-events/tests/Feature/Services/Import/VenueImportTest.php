<?php

namespace Tests\Feature\Services\Import;

use App\Models\Canton;
use App\Models\City;
use App\Models\ImportRun;
use App\Models\Source;
use App\Models\Venue;
use App\Services\Import\ImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Places import — sources that publish attractions/museums rather than dated
 * events, declared with config.content = "venues".
 */
class VenueImportTest extends TestCase
{
    use RefreshDatabase;

    private Canton $canton;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canton = Canton::create(['code' => 'UR', 'name' => 'Uri', 'slug' => 'uri']);
        City::create(['name' => 'Andermatt', 'slug' => 'andermatt', 'canton_id' => $this->canton->id]);
    }

    private function source(array $config = []): Source
    {
        return Source::factory()->trusted()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'content' => 'venues',
                'url' => 'https://api.example.org/v1/places',
                'items_path' => 'data',
                'field_map' => [
                    'external_id' => 'identifier',
                    'name' => 'name',
                    'description' => 'abstract',
                    'address' => 'address',
                    'lat' => 'geo.latitude',
                    'lng' => 'geo.longitude',
                    'website' => 'url',
                    'venue_type' => 'category',
                ],
                ...$config,
            ],
        ]);
    }

    public function test_it_imports_places_into_venues(): void
    {
        Http::fake(['api.example.org/*' => Http::response(['data' => [[
            'identifier' => 'abc-123',
            'name' => 'Schöllenen Gorge',
            'abstract' => 'A legendary section of the Gotthard route.',
            'address' => 'Schöllenenstrasse, Andermatt',
            'geo' => ['latitude' => 46.649147, 'longitude' => 8.590251],
            'url' => 'https://example.org/schoellenen',
            'category' => 'Historic monument',
        ]]])]);

        $run = app(ImportRunner::class)->run($this->source());

        $this->assertSame(ImportRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->items_created);

        $venue = Venue::first();
        $this->assertSame('Schöllenen Gorge', $venue->name);
        $this->assertSame('abc-123', $venue->source_external_id);
        $this->assertSame(46.649147, (float) $venue->lat);
        $this->assertSame(8.590251, (float) $venue->lng);
        $this->assertSame('https://example.org/schoellenen', $venue->website);
        // "Historic monument" maps onto the historical_building type.
        $this->assertSame(Venue::TYPE_HISTORICAL_BUILDING, $venue->venue_type);
        // Placed via the city named in its address.
        $this->assertSame($this->canton->id, $venue->canton_id);
    }

    /**
     * The Switzerland Tourism payload carries coordinates and no address
     * whatsoever, so text matching finds nothing and the canton — the site's
     * main filter — has to come from the point itself.
     */
    public function test_it_places_a_venue_that_has_coordinates_but_no_address(): void
    {
        Http::fake(['api.example.org/*' => Http::response(['data' => [[
            'identifier' => 'fff92c7b',
            'name' => 'Schöllenen Gorge',
            'geo' => ['latitude' => 46.649147, 'longitude' => 8.590251],
        ]]])]);

        app(ImportRunner::class)->run($this->source());

        $venue = Venue::first();
        $this->assertNull($venue->address);
        $this->assertSame($this->canton->id, $venue->canton_id, 'canton should be derived from coordinates');
    }

    public function test_it_reads_the_venue_type_out_of_a_classification_list(): void
    {
        Http::fake(['api.example.org/*' => Http::response(['data' => [[
            'identifier' => 'x1',
            'name' => 'Devil\'s Bridge',
            'classification' => [
                ['name' => 'seasons', 'values' => [['name' => 'winter', 'title' => 'Winter']]],
                ['name' => 'buildingstype', 'values' => [['name' => 'bridge', 'title' => 'Bridge']]],
            ],
        ]]])]);

        app(ImportRunner::class)->run($this->source([
            'field_map' => [
                'external_id' => 'identifier',
                'name' => 'name',
                'venue_type_classifications' => ['buildingstype', 'naturetype'],
            ],
        ]));

        $this->assertSame(Venue::TYPE_HISTORICAL_BUILDING, Venue::first()->venue_type);
    }

    public function test_places_from_an_untrusted_source_wait_as_drafts(): void
    {
        Http::fake(['api.example.org/*' => Http::response(['data' => [
            ['identifier' => 'x1', 'name' => 'Unvetted Place'],
        ]])]);

        $source = $this->source();
        $source->update(['trust_level' => Source::TRUST_UNTRUSTED]);

        app(ImportRunner::class)->run($source);

        $this->assertSame('draft', Venue::first()->status);
    }

    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $payload = fn (string $name) => ['data' => [[
            'identifier' => 'abc-123',
            'name' => $name,
            'geo' => ['latitude' => 46.6, 'longitude' => 8.5],
        ]]];

        Http::fake(['api.example.org/*' => Http::sequence()
            ->push($payload('Old Name'))
            ->push($payload('New Name')),
        ]);

        $source = $this->source();

        app(ImportRunner::class)->run($source);
        $second = app(ImportRunner::class)->run($source);

        $this->assertSame(1, Venue::count());
        $this->assertSame(1, $second->items_updated);
        $this->assertSame('New Name', Venue::first()->name);
    }

    public function test_it_follows_link_style_pagination(): void
    {
        Http::fake(['api.example.org/*' => Http::sequence()
            ->push([
                'data' => [['identifier' => 'a', 'name' => 'First']],
                'links' => ['next' => 'https://api.example.org/v1/places?page=1'],
            ])
            ->push([
                'data' => [['identifier' => 'b', 'name' => 'Second']],
                'links' => [],
            ]),
        ]);

        $run = app(ImportRunner::class)->run($this->source([
            'pagination' => ['mode' => 'link', 'next_path' => 'links.next', 'max_pages' => 10],
        ]));

        $this->assertSame(2, $run->items_created);
        $this->assertEqualsCanonicalizing(['First', 'Second'], Venue::pluck('name')->all());
    }

    /**
     * Regression: the first live import re-fetched page 1 twenty-one times.
     * Passing a query array to ->get() makes Guzzle *replace* the URI's query
     * string, so the page number carried by links.next was being discarded.
     * The existing link-mode test missed it because its fake next URL had no
     * query string of its own.
     */
    public function test_following_a_next_link_keeps_that_links_query_parameters(): void
    {
        Http::fake(['api.example.org/*' => Http::sequence()
            ->push([
                'data' => [['identifier' => 'a', 'name' => 'First']],
                'links' => ['next' => 'https://api.example.org/v1/places?hitsPerPage=50&page=1'],
            ])
            ->push([
                'data' => [['identifier' => 'b', 'name' => 'Second']],
                'links' => [],
            ]),
        ]);

        app(ImportRunner::class)->run($this->source([
            'query' => ['hitsPerPage' => 100],
            'pagination' => ['mode' => 'link', 'next_path' => 'links.next', 'max_pages' => 10],
        ]));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'page=1'));
        $this->assertEqualsCanonicalizing(['First', 'Second'], Venue::pluck('name')->all());
    }

    public function test_a_next_link_that_does_not_advance_stops_the_run(): void
    {
        Http::fake(['api.example.org/*' => Http::response([
            'data' => [['identifier' => 'a', 'name' => 'Groundhog']],
            // Points at the URL just fetched — without a guard this burns
            // every one of max_pages on identical requests.
            'links' => ['next' => 'https://api.example.org/v1/places'],
        ])]);

        $run = app(ImportRunner::class)->run($this->source([
            'pagination' => ['mode' => 'link', 'next_path' => 'links.next', 'max_pages' => 50],
        ]));

        $this->assertSame(1, $run->items_seen);
    }

    public function test_pagination_stops_at_max_pages(): void
    {
        // A feed that genuinely keeps advancing must still be capped — here the
        // next link always moves on, so max_pages is the only thing that ends it.
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 0);

            return Http::response([
                'data' => [['identifier' => "id-{$page}", 'name' => "Place {$page}"]],
                'links' => ['next' => 'https://api.example.org/v1/places?page='.($page + 1)],
            ]);
        });

        $run = app(ImportRunner::class)->run($this->source([
            'pagination' => ['mode' => 'link', 'next_path' => 'links.next', 'max_pages' => 3],
        ]));

        $this->assertSame(3, $run->items_seen);
    }

    public function test_it_resolves_api_keys_from_config_not_the_database(): void
    {
        config(['services.sources.secrets.demo' => 'super-secret-value']);

        Http::fake(['api.example.org/*' => Http::response(['data' => [
            ['identifier' => 'a', 'name' => 'Place'],
        ]])]);

        app(ImportRunner::class)->run($this->source([
            'headers' => ['x-api-key' => 'secret:demo'],
        ]));

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'super-secret-value'));
    }

    /**
     * The first live run imported 1,050 records and then hit an error on a
     * later page, and was reported as an outright failure — which both hid the
     * work done and made it indistinguishable from a run that achieved nothing.
     */
    public function test_a_failure_after_some_items_is_reported_as_partial(): void
    {
        Http::fake(['api.example.org/*' => Http::sequence()
            ->push([
                'data' => [['identifier' => 'a', 'name' => 'Imported']],
                'links' => ['next' => 'https://api.example.org/v1/places?page=1'],
            ])
            ->push(status: 500)
            ->push(status: 500)
            ->push(status: 500),
        ]);

        $run = app(ImportRunner::class)->run($this->source([
            'pagination' => ['mode' => 'link', 'next_path' => 'links.next', 'max_pages' => 10],
        ]));

        $this->assertSame(ImportRun::STATUS_PARTIAL, $run->status);
        $this->assertSame(1, $run->items_created);
        $this->assertStringContainsString('Fetch failed', $run->error_log[0]['message']);
        $this->assertSame('Imported', Venue::first()->name);
    }

    public function test_a_run_that_fails_before_any_item_is_still_a_failure(): void
    {
        Http::fake(['api.example.org/*' => Http::response(status: 500)]);

        $run = app(ImportRunner::class)->run($this->source());

        $this->assertSame(ImportRun::STATUS_FAILED, $run->status);
    }

    public function test_a_missing_secret_fails_the_run_with_a_clear_message(): void
    {
        config(['services.sources.secrets.demo' => null]);

        Http::fake(['api.example.org/*' => Http::response(['data' => []])]);

        $run = app(ImportRunner::class)->run($this->source([
            'headers' => ['x-api-key' => 'secret:demo'],
        ]));

        $this->assertSame(ImportRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('references secret [demo]', $run->error_log[0]['message']);
    }
}
