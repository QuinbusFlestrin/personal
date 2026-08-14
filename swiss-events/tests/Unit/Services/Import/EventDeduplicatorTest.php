<?php

namespace Tests\Unit\Services\Import;

use App\Models\Event;
use App\Models\Source;
use App\Services\Import\Dedup\EventDeduplicator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDeduplicatorTest extends TestCase
{
    use RefreshDatabase;

    private EventDeduplicator $deduplicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deduplicator = new EventDeduplicator;
    }

    public function test_hash_is_stable_for_identical_input(): void
    {
        $date = CarbonImmutable::parse('2026-09-01 20:00:00');

        $hashA = $this->deduplicator->hash('Jazz Night', $date, null, 'Kaufleuten');
        $hashB = $this->deduplicator->hash('Jazz Night', $date, null, 'Kaufleuten');

        $this->assertSame($hashA, $hashB);
    }

    public function test_hash_ignores_case_accents_and_punctuation(): void
    {
        $date = CarbonImmutable::parse('2026-09-01 20:00:00');

        $hashA = $this->deduplicator->hash('Café-Konzert!', $date, null, 'Zürich');
        $hashB = $this->deduplicator->hash('cafe konzert', $date, null, 'zurich');

        $this->assertSame($hashA, $hashB);
    }

    public function test_hash_differs_when_venue_differs(): void
    {
        $date = CarbonImmutable::parse('2026-09-01 20:00:00');

        $hashA = $this->deduplicator->hash('Jazz Night', $date, null, 'Kaufleuten');
        $hashB = $this->deduplicator->hash('Jazz Night', $date, null, 'X-tra');

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_hash_differs_when_date_differs(): void
    {
        $hashA = $this->deduplicator->hash('Jazz Night', CarbonImmutable::parse('2026-09-01'), null, 'Kaufleuten');
        $hashB = $this->deduplicator->hash('Jazz Night', CarbonImmutable::parse('2026-09-02'), null, 'Kaufleuten');

        $this->assertNotSame($hashA, $hashB);
    }

    public function test_find_duplicate_matches_on_hash(): void
    {
        $event = Event::factory()->create(['dedup_hash' => 'abc123']);

        $found = $this->deduplicator->findDuplicate('abc123');

        $this->assertTrue($found->is($event));
    }

    public function test_find_duplicate_excludes_the_given_source(): void
    {
        $source = Source::factory()->create();
        Event::factory()->create(['dedup_hash' => 'abc123', 'source_id' => $source->id]);

        $found = $this->deduplicator->findDuplicate('abc123', excludingSourceId: $source->id);

        $this->assertNull($found);
    }

    public function test_find_duplicate_still_matches_a_different_source(): void
    {
        $sourceA = Source::factory()->create();
        $sourceB = Source::factory()->create();
        $event = Event::factory()->create(['dedup_hash' => 'abc123', 'source_id' => $sourceA->id]);

        $found = $this->deduplicator->findDuplicate('abc123', excludingSourceId: $sourceB->id);

        $this->assertTrue($found->is($event));
    }
}
