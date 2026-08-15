<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InspectSourceTest extends TestCase
{
    /**
     * The first run of this command against six real sites recommended
     * "json_api" three times on the strength of a favicon manifest and a Google
     * Maps callback — a confident recommendation built on noise.
     */
    public function test_asset_urls_are_not_mistaken_for_data_endpoints(): void
    {
        Http::fake([
            'site.example.org/robots.txt' => Http::response(''),
            'site.example.org/agenda' => Http::response(<<<'HTML'
            <html><head>
            <link rel="manifest" href="/favicons/manifest.aab33c.json">
            <script src="https://maps.googleapis.com/mapsjs?v=3&callback=initMaps"></script>
            <script src="/static/app.min.js"></script>
            </head><body></body></html>
            HTML),
        ]);

        $this->artisan('sources:inspect', ['url' => 'https://site.example.org/agenda'])
            ->expectsOutputToContain('Possible JSON endpoints: none.')
            ->expectsOutputToContain('Recommended source type: scraper')
            ->assertSuccessful();
    }

    public function test_a_genuine_api_endpoint_is_still_reported(): void
    {
        Http::fake([
            'site.example.org/robots.txt' => Http::response(''),
            'site.example.org/agenda' => Http::response(
                '<html><body><script>fetch("/api/events?from=today")</script></body></html>'
            ),
        ]);

        $this->artisan('sources:inspect', ['url' => 'https://site.example.org/agenda'])
            ->expectsOutputToContain('/api/events')
            ->expectsOutputToContain('Recommended source type: json_api')
            ->assertSuccessful();
    }

    public function test_it_reports_a_disallowed_url_before_anything_else(): void
    {
        Http::fake([
            'site.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /agenda"),
            'site.example.org/agenda' => Http::response('<html></html>'),
        ]);

        $this->artisan('sources:inspect', ['url' => 'https://site.example.org/agenda'])
            ->expectsOutputToContain('DISALLOWED')
            ->assertSuccessful();
    }

    public function test_embedded_events_win_over_endpoint_guesses(): void
    {
        Http::fake([
            'site.example.org/robots.txt' => Http::response(''),
            'site.example.org/agenda' => Http::response(<<<'HTML'
            <html><head><script type="application/ld+json">
            {"@type":"MusicEvent","name":"Gig","startDate":"2026-09-01T20:00:00Z"}
            </script></head><body></body></html>
            HTML),
        ]);

        $this->artisan('sources:inspect', ['url' => 'https://site.example.org/agenda'])
            ->expectsOutputToContain('found 1 schema.org Event')
            ->expectsOutputToContain('Recommended source type: json_ld')
            ->assertSuccessful();
    }
}
