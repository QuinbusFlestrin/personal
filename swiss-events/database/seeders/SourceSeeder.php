<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Real sources. Anything requiring a key references it as "secret:<name>"
     * and resolves from config/services.php, so no credential is ever stored in
     * the database or shown in the admin UI.
     */
    public function run(): void
    {
        // Placeholders from the initial scaffold; they pointed at example.org
        // and only confuse the source list now that real sources exist.
        Source::whereIn('name', [
            'MySwitzerland Events (placeholder)',
            'Zürich Tourism Agenda (placeholder)',
            'Genève Tourisme Agenda (placeholder)',
        ])->delete();

        $sources = [
            [
                'name' => 'Switzerland Tourism — Attractions',
                'type' => Source::TYPE_JSON_API,
                'config' => [
                    // Places, not dated events: this is the museums/landmarks
                    // /natural-sights half of the site.
                    'content' => 'venues',
                    'url' => 'https://opendata.myswitzerland.io/v1/attractions',
                    'items_path' => 'data',
                    'headers' => ['x-api-key' => 'secret:myswitzerland'],
                    'query' => ['hitsPerPage' => 100],
                    // The API returns links.next until the last page; max_pages
                    // is only a stop-loss, not the expected page count.
                    'pagination' => [
                        'mode' => 'link',
                        'next_path' => 'links.next',
                        'max_pages' => 100,
                    ],
                    'field_map' => [
                        'external_id' => 'identifier',
                        'name' => 'name',
                        'description' => 'abstract',
                        // No address is published — only a point, which is why
                        // the canton is derived from coordinates.
                        'lat' => 'geo.latitude',
                        'lng' => 'geo.longitude',
                        'website' => 'url',
                        'image' => 'photo',
                        // Venue type sits inside the classification list rather
                        // than in a field of its own.
                        'venue_type_classifications' => ['buildingstype', 'naturetype'],
                    ],
                    // CC BY-SA 4.0 permits reuse only with credit, so every
                    // page built from this data carries the line below.
                    'attribution' => [
                        'text' => 'Switzerland Tourism (MySwitzerland OpenData)',
                        'url' => 'https://www.myswitzerland.com',
                        'licence' => 'CC BY-SA 4.0',
                        'licence_url' => 'http://creativecommons.org/licenses/by-sa/4.0/',
                    ],
                ],
                'trust_level' => Source::TRUST_TRUSTED,
                'is_active' => true,
            ],
            [
                'name' => 'Ticketmaster CH — Events',
                'type' => Source::TYPE_JSON_API,
                'config' => [
                    'url' => 'https://app.ticketmaster.com/discovery/v2/events.json',
                    'items_path' => '_embedded.events',
                    // Ticketmaster takes its key as a query parameter, not a
                    // header — resolved from config/services.php all the same.
                    'query' => [
                        'apikey' => 'secret:ticketmaster',
                        'countryCode' => 'CH',
                    ],
                    // Page mode, not link mode: their _links.next.href is a
                    // relative path, which link mode would treat as absolute.
                    // Their deep-paging cap is page*size <= 1000, so 5 x 200
                    // is the reachable window rather than the whole catalogue.
                    'pagination' => [
                        'mode' => 'page',
                        'param' => 'page',
                        'size_param' => 'size',
                        'page_size' => 200,
                        'start' => 0,
                        'max_pages' => 5,
                    ],
                    'field_map' => [
                        'external_id' => 'id',
                        'title' => 'name',
                        'starts_at' => 'dates.start.dateTime',
                        'ends_at' => 'dates.end.dateTime',
                        'external_url' => 'url',
                        'image' => 'images.0.url',
                        'venue_name' => '_embedded.venues.0.name',
                        'venue_address' => '_embedded.venues.0.address.line1',
                        'price_info' => 'priceRanges.0.min',
                        'category_hint' => 'classifications.0.segment.name',
                    ],
                    'category_map' => [
                        'Music' => 'Concerts',
                        'Arts & Theatre' => 'Theatre & Shows',
                        'Sports' => 'Sports',
                        'Film' => 'Theatre & Shows',
                    ],
                    'attribution' => [
                        'text' => 'Ticketmaster',
                        'url' => 'https://www.ticketmaster.ch',
                    ],
                ],
                // Untrusted to begin with: the first runs land in the review
                // queue so the mapping can be judged before anything publishes.
                'trust_level' => Source::TRUST_UNTRUSTED,
                'is_active' => true,
            ],
            [
                'name' => 'Songkick — Geneva',
                'type' => Source::TYPE_JSON_LD,
                'config' => [
                    // Their metro-area pages embed schema.org Event markup with
                    // venue name, street address, postcode and coordinates —
                    // richer than most sites' own listings, and no key needed.
                    // robots.txt permits this path for our crawler.
                    'url' => 'https://www.songkick.com/metro-areas/27453-switzerland-geneva',
                    'attribution' => [
                        'text' => 'Songkick',
                        'url' => 'https://www.songkick.com',
                    ],
                ],
                'trust_level' => Source::TRUST_UNTRUSTED,
                'is_active' => true,
            ],
            [
                'name' => 'Manual entries',
                'type' => Source::TYPE_MANUAL,
                'config' => [],
                'trust_level' => Source::TRUST_TRUSTED,
                'is_active' => true,
            ],
        ];

        foreach ($sources as $source) {
            Source::updateOrCreate(['name' => $source['name']], $source);
        }
    }
}
