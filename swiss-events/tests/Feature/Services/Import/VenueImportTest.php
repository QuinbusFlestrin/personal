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
        return Source::factory()->create([
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

    public function test_pagination_stops_at_max_pages(): void
    {
        // A feed whose "next" link never terminates must not loop forever.
        Http::fake(['api.example.org/*' => Http::response([
            'data' => [['identifier' => uniqid(), 'name' => 'Endless']],
            'links' => ['next' => 'https://api.example.org/v1/places?page=99'],
        ])]);

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
