<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Event;
use App\Models\ImportRun;
use App\Models\Source;
use App\Models\Venue;
use App\Services\Import\Connectors\IcalConnector;
use App\Services\Import\Connectors\JsonApiConnector;
use App\Services\Import\Connectors\JsonLdConnector;
use App\Services\Import\Connectors\RssConnector;
use App\Services\Import\Connectors\ScraperConnector;
use App\Services\Import\Contracts\SourceConnector;
use App\Services\Import\Dedup\EventDeduplicator;
use App\Services\Import\DTO\RawEventDTO;
use App\Services\Import\Support\VenueLocationResolver;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orchestrates one source's import: fetch -> normalize (already done by the
 * connector) -> resolve venue/category -> dedup -> upsert -> status-by-trust.
 * Every item is isolated in its own try/catch so one bad record — or even a
 * fully broken feed — never aborts the batch or takes down other sources.
 */
class ImportRunner
{
    public function __construct(
        private readonly EventDeduplicator $deduplicator,
        private readonly VenueLocationResolver $locations,
    ) {}

    public function run(Source $source): ImportRun
    {
        $importRun = ImportRun::create([
            'source_id' => $source->id,
            'started_at' => now(),
            'status' => ImportRun::STATUS_RUNNING,
        ]);

        $counts = [
            'items_seen' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_skipped_duplicate' => 0,
            'items_failed' => 0,
        ];
        $errors = [];

        try {
            $connector = $this->resolveConnector($source);

            foreach ($connector->fetch($source) as $dto) {
                $counts['items_seen']++;

                try {
                    $counts[$this->processItem($source, $dto)]++;
                } catch (Throwable $e) {
                    $counts['items_failed']++;
                    $errors[] = ['title' => $dto->title, 'message' => $e->getMessage()];
                }
            }

            $status = $this->determineStatus($counts);
        } catch (Throwable $e) {
            $status = ImportRun::STATUS_FAILED;
            $errors[] = ['message' => 'Fetch failed: '.$e->getMessage()];
        }

        $importRun->update([...$counts, 'finished_at' => now(), 'status' => $status, 'error_log' => $errors]);
        $source->update(['last_run_at' => now(), 'last_run_status' => $status]);

        return $importRun;
    }

    private function resolveConnector(Source $source): SourceConnector
    {
        return match ($source->type) {
            Source::TYPE_RSS => app(RssConnector::class),
            Source::TYPE_ICAL => app(IcalConnector::class),
            Source::TYPE_JSON_API => app(JsonApiConnector::class),
            Source::TYPE_SCRAPER => app(ScraperConnector::class),
            Source::TYPE_JSON_LD => app(JsonLdConnector::class),
            default => throw new RuntimeException("No connector registered for source type [{$source->type}]"),
        };
    }

    /**
     * @return string 'items_created'|'items_updated'|'items_skipped_duplicate'
     */
    private function processItem(Source $source, RawEventDTO $dto): string
    {
        $venue = $this->resolveVenue($dto);
        $category = $this->resolveCategory($dto);
        $hash = $this->deduplicator->hash($dto->title, $dto->startsAt, $venue?->id, $dto->venueName);

        $attributes = [
            'title' => $dto->title,
            'description' => $dto->description,
            'venue_id' => $venue?->id,
            'category_id' => $category?->id,
            'canton_id' => $venue?->canton_id,
            'starts_at' => $dto->startsAt,
            'ends_at' => $dto->endsAt,
            'price_info' => $dto->priceInfo,
            'external_url' => $dto->externalUrl,
            'image' => $dto->image,
            'dedup_hash' => $hash,
        ];

        // Layer 1: deterministic (source_id, source_external_id) key — re-imports upsert.
        if ($dto->externalId) {
            $existing = Event::query()
                ->where('source_id', $source->id)
                ->where('source_external_id', $dto->externalId)
                ->first();

            if ($existing) {
                $existing->update($attributes);

                return 'items_updated';
            }
        }

        // Layer 2: fuzzy cross-source/no-id dedup.
        if ($this->deduplicator->findDuplicate($hash, $source->id)) {
            return 'items_skipped_duplicate';
        }

        Event::create([
            ...$attributes,
            'slug' => $this->uniqueSlug($dto->title, $dto->startsAt->toDateString()),
            'source_id' => $source->id,
            'source_external_id' => $dto->externalId,
            'status' => $source->isTrusted() ? Event::STATUS_PUBLISHED : Event::STATUS_PENDING_REVIEW,
        ]);

        return 'items_created';
    }

    private function resolveVenue(RawEventDTO $dto): ?Venue
    {
        if (! $dto->venueName) {
            return null;
        }

        $location = $this->locations->resolve($dto->venueAddress, $dto->venueName);

        $venue = Venue::firstOrCreate(
            ['slug' => Str::slug($dto->venueName)],
            [
                'name' => $dto->venueName,
                'address' => $dto->venueAddress,
                'city_id' => $location['city_id'],
                'canton_id' => $location['canton_id'],
                'venue_type' => Venue::TYPE_GENERIC,
                'status' => 'published',
            ]
        );

        // A venue first seen through a source that published no address can be
        // placed later by a richer source, so backfill rather than leave it
        // permanently unfilterable. Existing values are never overwritten.
        if ($venue->canton_id === null && $location['canton_id'] !== null) {
            $venue->update(array_filter([
                'address' => $venue->address ?? $dto->venueAddress,
                'city_id' => $location['city_id'],
                'canton_id' => $location['canton_id'],
            ], fn ($value) => $value !== null));
        }

        return $venue;
    }

    private function resolveCategory(RawEventDTO $dto): ?Category
    {
        if (! $dto->categoryHint) {
            return null;
        }

        $slug = Str::slug($dto->categoryHint);

        return Category::where('slug', $slug)->orWhere('name', $dto->categoryHint)->first();
    }

    private function uniqueSlug(string $title, string $dateSuffix): string
    {
        $base = Str::slug("{$title} {$dateSuffix}");
        $slug = $base;
        $attempt = 1;

        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$attempt;
        }

        return $slug;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function determineStatus(array $counts): string
    {
        if ($counts['items_failed'] === 0) {
            return ImportRun::STATUS_SUCCESS;
        }

        $succeeded = $counts['items_created'] + $counts['items_updated'] + $counts['items_skipped_duplicate'];

        return $succeeded > 0 ? ImportRun::STATUS_PARTIAL : ImportRun::STATUS_FAILED;
    }
}
