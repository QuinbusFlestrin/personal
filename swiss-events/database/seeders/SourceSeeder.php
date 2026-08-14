<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Starter sources for the MVP. Each is seeded inactive with a placeholder
     * config — an admin must verify the real feed URL/mapping in Filament and
     * flip `is_active` (and `trust_level`, once its output has been reviewed
     * a few times) before it starts contributing events.
     */
    public function run(): void
    {
        $sources = [
            [
                'name' => 'MySwitzerland Events (placeholder)',
                'type' => Source::TYPE_JSON_API,
                'config' => [
                    'url' => 'https://example.org/replace-with-real-myswitzerland-events-endpoint',
                    'field_map' => [
                        'external_id' => 'id',
                        'title' => 'name',
                        'description' => 'description',
                        'starts_at' => 'startDate',
                        'ends_at' => 'endDate',
                        'venue_name' => 'location.name',
                        'external_url' => 'url',
                    ],
                ],
                'trust_level' => Source::TRUST_UNTRUSTED,
                'is_active' => false,
            ],
            [
                'name' => 'Zürich Tourism Agenda (placeholder)',
                'type' => Source::TYPE_RSS,
                'config' => [
                    'url' => 'https://example.org/replace-with-real-zuerich-tourism-rss',
                ],
                'trust_level' => Source::TRUST_UNTRUSTED,
                'is_active' => false,
            ],
            [
                'name' => 'Genève Tourisme Agenda (placeholder)',
                'type' => Source::TYPE_ICAL,
                'config' => [
                    'url' => 'https://example.org/replace-with-real-geneve-tourisme-ical',
                ],
                'trust_level' => Source::TRUST_UNTRUSTED,
                'is_active' => false,
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
