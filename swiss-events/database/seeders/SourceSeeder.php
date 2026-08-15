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
