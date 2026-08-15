<?php

namespace App\Console\Commands;

use App\Models\ImportRun;
use App\Models\Source;
use App\Services\Import\ImportRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('events:import
    {--source= : Only import the source with this ID (ignores the staleness guard)}
    {--force : Import every active source now, ignoring the staleness guard}')]
#[Description('Run all active sources through the import pipeline (or a single source with --source=ID)')]
class ImportEvents extends Command
{
    /**
     * How long a source rests after a run that produced data. The scheduler
     * ticks hourly (see routes/console.php) but each source is only actually
     * fetched once a day; 20h rather than 24h so the daily import can't drift
     * later and later, skipping a day whenever a tick runs slightly early.
     */
    private const RERUN_AFTER_HOURS = 20;

    /**
     * Failed sources retry sooner than successful ones — a transient outage
     * shouldn't cost a full day — but still back off, so a persistently broken
     * feed is polled a handful of times a day rather than every hour.
     */
    private const RETRY_FAILED_AFTER_HOURS = 3;

    public function handle(ImportRunner $runner): int
    {
        $sources = Source::query()
            ->when(
                $this->option('source'),
                fn ($query, $id) => $query->whereKey($id),
                // "manual" sources represent admin-entered events, not a feed to
                // fetch — they're excluded from the automated batch by default.
                fn ($query) => $query->where('is_active', true)->where('type', '!=', Source::TYPE_MANUAL),
            )
            ->get();

        // An explicit --source or --force is a human asking for this run now;
        // the guard only governs the unattended hourly tick.
        if (! $this->option('source') && ! $this->option('force')) {
            $sources = $sources->filter(fn (Source $source) => $this->isDue($source));
        }

        if ($sources->isEmpty()) {
            $this->warn('No sources due for import.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            $this->line("Importing [{$source->name}]...");

            try {
                $run = $runner->run($source);
                $this->info("  {$run->status}: seen={$run->items_seen} created={$run->items_created} updated={$run->items_updated} duplicates={$run->items_skipped_duplicate} failed={$run->items_failed}");
            } catch (Throwable $e) {
                // ImportRunner already isolates per-item and per-connector failures into
                // the ImportRun record; this catch is a last-resort guard so one source
                // throwing unexpectedly (e.g. a DB error) never aborts the remaining sources.
                $this->error("  Unexpected failure importing source #{$source->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function isDue(Source $source): bool
    {
        if ($source->last_run_at === null) {
            return true;
        }

        $restHours = in_array($source->last_run_status, [ImportRun::STATUS_SUCCESS, ImportRun::STATUS_PARTIAL], true)
            ? self::RERUN_AFTER_HOURS
            : self::RETRY_FAILED_AFTER_HOURS;

        return $source->last_run_at->lessThanOrEqualTo(now()->subHours($restHours));
    }
}
