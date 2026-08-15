<?php

namespace Tests\Feature\Console;

use App\Models\ImportRun;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The scheduler ticks hourly (routes/console.php) because shared hosting can't
 * guarantee which minute the cron trigger lands on. These tests pin the guard
 * that turns those hourly ticks back into one import per source per day.
 */
class ImportEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.example.org/*' => Http::response([
            'items' => [['id' => 'ext-1', 'name' => 'Jazz Night', 'start' => '2026-09-01T20:00:00Z']],
        ])]);
    }

    private function source(array $attributes = []): Source
    {
        return Source::factory()->create([
            'type' => Source::TYPE_JSON_API,
            'config' => [
                'url' => 'https://api.example.org/events',
                'items_path' => 'items',
                'field_map' => ['external_id' => 'id', 'title' => 'name', 'starts_at' => 'start'],
            ],
            ...$attributes,
        ]);
    }

    public function test_it_imports_a_source_that_has_never_run(): void
    {
        $source = $this->source(['last_run_at' => null]);

        $this->artisan('events:import')->assertSuccessful();

        $this->assertDatabaseHas('import_runs', ['source_id' => $source->id]);
    }

    public function test_it_skips_a_source_that_succeeded_recently(): void
    {
        $source = $this->source([
            'last_run_at' => now()->subHours(2),
            'last_run_status' => ImportRun::STATUS_SUCCESS,
        ]);

        $this->artisan('events:import')->assertSuccessful();

        $this->assertDatabaseMissing('import_runs', ['source_id' => $source->id]);
    }

    public function test_it_imports_a_source_whose_last_success_has_gone_stale(): void
    {
        $source = $this->source([
            'last_run_at' => now()->subHours(21),
            'last_run_status' => ImportRun::STATUS_SUCCESS,
        ]);

        $this->artisan('events:import')->assertSuccessful();

        $this->assertDatabaseHas('import_runs', ['source_id' => $source->id]);
    }

    public function test_a_failed_source_retries_sooner_than_a_successful_one(): void
    {
        $recent = $this->source([
            'last_run_at' => now()->subHour(),
            'last_run_status' => ImportRun::STATUS_FAILED,
        ]);
        $backedOff = $this->source([
            'last_run_at' => now()->subHours(4),
            'last_run_status' => ImportRun::STATUS_FAILED,
        ]);

        $this->artisan('events:import')->assertSuccessful();

        // Still inside the failure backoff window.
        $this->assertDatabaseMissing('import_runs', ['source_id' => $recent->id]);
        $this->assertDatabaseHas('import_runs', ['source_id' => $backedOff->id]);
    }

    public function test_force_ignores_the_staleness_guard(): void
    {
        $source = $this->source([
            'last_run_at' => now()->subMinutes(5),
            'last_run_status' => ImportRun::STATUS_SUCCESS,
        ]);

        $this->artisan('events:import', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('import_runs', ['source_id' => $source->id]);
    }

    public function test_targeting_one_source_ignores_the_staleness_guard(): void
    {
        $source = $this->source([
            'last_run_at' => now()->subMinutes(5),
            'last_run_status' => ImportRun::STATUS_SUCCESS,
        ]);

        $this->artisan('events:import', ['--source' => $source->id])->assertSuccessful();

        $this->assertDatabaseHas('import_runs', ['source_id' => $source->id]);
    }

    public function test_manual_sources_are_never_fetched_automatically(): void
    {
        $manual = $this->source(['type' => Source::TYPE_MANUAL, 'last_run_at' => null]);

        $this->artisan('events:import')->assertSuccessful();

        $this->assertDatabaseMissing('import_runs', ['source_id' => $manual->id]);
    }
}
