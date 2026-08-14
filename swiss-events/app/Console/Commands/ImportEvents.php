<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Import\ImportRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('events:import {--source= : Only import the source with this ID}')]
#[Description('Run all active sources through the import pipeline (or a single source with --source=ID)')]
class ImportEvents extends Command
{
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

        if ($sources->isEmpty()) {
            $this->warn('No active sources to import.');

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
}
