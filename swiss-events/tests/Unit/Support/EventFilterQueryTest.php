<?php

namespace Tests\Unit\Support;

use App\Models\Canton;
use App\Models\Category;
use App\Models\Event;
use App\Support\EventFilterQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFilterQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_events_are_returned(): void
    {
        Event::factory()->published()->create(['title' => 'Visible']);
        Event::factory()->pendingReview()->create(['title' => 'Hidden']);

        $results = EventFilterQuery::apply(Event::query(), [])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Visible', $results->first()->title);
    }

    public function test_past_events_are_excluded_by_default(): void
    {
        Event::factory()->published()->create(['title' => 'Past', 'starts_at' => now()->subWeek()]);
        Event::factory()->published()->create(['title' => 'Future', 'starts_at' => now()->addWeek()]);

        $results = EventFilterQuery::apply(Event::query(), [])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Future', $results->first()->title);
    }

    public function test_filters_by_category(): void
    {
        $concerts = Category::factory()->create();
        $theatre = Category::factory()->create();

        Event::factory()->published()->create(['category_id' => $concerts->id]);
        Event::factory()->published()->create(['category_id' => $theatre->id]);

        $results = EventFilterQuery::apply(Event::query(), ['category_ids' => [$concerts->id]])->get();

        $this->assertCount(1, $results);
        $this->assertSame($concerts->id, $results->first()->category_id);
    }

    public function test_filters_by_canton(): void
    {
        $zh = Canton::factory()->create();
        $ge = Canton::factory()->create();

        Event::factory()->published()->create(['canton_id' => $zh->id]);
        Event::factory()->published()->create(['canton_id' => $ge->id]);

        $results = EventFilterQuery::apply(Event::query(), ['canton_ids' => [$zh->id]])->get();

        $this->assertCount(1, $results);
        $this->assertSame($zh->id, $results->first()->canton_id);
    }

    public function test_filters_by_date_range(): void
    {
        Event::factory()->published()->create(['title' => 'Too early', 'starts_at' => now()->addDays(1)]);
        Event::factory()->published()->create(['title' => 'In range', 'starts_at' => now()->addDays(5)]);
        Event::factory()->published()->create(['title' => 'Too late', 'starts_at' => now()->addDays(20)]);

        $results = EventFilterQuery::apply(Event::query(), [
            'from' => now()->addDays(3)->toDateString(),
            'to' => now()->addDays(10)->toDateString(),
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame('In range', $results->first()->title);
    }

    public function test_filters_by_search_keyword_across_title_and_description(): void
    {
        Event::factory()->published()->create(['title' => 'Jazz Night', 'description' => 'Live music']);
        Event::factory()->published()->create(['title' => 'Art Fair', 'description' => 'Featuring jazz-inspired paintings']);
        Event::factory()->published()->create(['title' => 'Theatre Show', 'description' => 'A play']);

        $results = EventFilterQuery::apply(Event::query(), ['search' => 'jazz'])->get();

        $this->assertCount(2, $results);
    }

    public function test_filters_combine_with_and_semantics(): void
    {
        $concerts = Category::factory()->create();
        $zh = Canton::factory()->create();
        $ge = Canton::factory()->create();

        Event::factory()->published()->create(['category_id' => $concerts->id, 'canton_id' => $zh->id, 'title' => 'Match']);
        Event::factory()->published()->create(['category_id' => $concerts->id, 'canton_id' => $ge->id, 'title' => 'Wrong canton']);

        $results = EventFilterQuery::apply(Event::query(), [
            'category_ids' => [$concerts->id],
            'canton_ids' => [$zh->id],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertSame('Match', $results->first()->title);
    }
}
