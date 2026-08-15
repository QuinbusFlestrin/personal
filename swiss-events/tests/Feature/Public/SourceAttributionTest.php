<?php

namespace Tests\Feature\Public;

use App\Models\Source;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Open datasets are commonly CC BY-SA, which permits reuse only with
 * attribution — so publishing an imported place without its credit line is a
 * licence breach, not a cosmetic omission.
 */
class SourceAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_place_page_credits_its_source(): void
    {
        $source = Source::factory()->create([
            'config' => [
                'attribution' => [
                    'text' => 'Switzerland Tourism',
                    'url' => 'https://www.myswitzerland.com',
                    'licence' => 'CC BY-SA 4.0',
                    'licence_url' => 'http://creativecommons.org/licenses/by-sa/4.0/',
                ],
            ],
        ]);

        $venue = Venue::factory()->create(['source_id' => $source->id, 'status' => 'published']);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertSee('Switzerland Tourism')
            ->assertSee('CC BY-SA 4.0');
    }

    public function test_a_place_without_an_attributed_source_shows_no_credit_line(): void
    {
        $venue = Venue::factory()->create(['source_id' => null, 'status' => 'published']);

        $this->get(route('venues.show', $venue->slug))
            ->assertOk()
            ->assertDontSee('Data source:');
    }
}
