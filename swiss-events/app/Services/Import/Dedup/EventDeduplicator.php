<?php

namespace App\Services\Import\Dedup;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Two-layer dedup, applied uniformly regardless of source trust level:
 *
 *  1. Deterministic: unique (source_id, source_external_id) — handled by
 *     ImportRunner's upsert-by-key before this class is even consulted.
 *  2. Fuzzy fallback: a hash of normalized(title) + date + venue, used to
 *     catch cross-source duplicates and sources without a stable id.
 *
 * Kept intentionally simple (exact hash match, not similarity scoring) —
 * see the plan's Phase 3 for fuzzy/similarity matching.
 */
class EventDeduplicator
{
    public function hash(string $title, CarbonInterface $startsAt, ?int $venueId, ?string $venueName = null): string
    {
        $normalizedTitle = $this->normalize($title);
        $date = $startsAt->toDateString();
        $venueKey = $venueId !== null ? "v{$venueId}" : $this->normalize((string) $venueName);

        return hash('sha256', "{$normalizedTitle}|{$date}|{$venueKey}");
    }

    /**
     * Find an existing event with the same dedup hash, excluding a given
     * source (that case is already covered by the deterministic key).
     */
    public function findDuplicate(string $dedupHash, ?int $excludingSourceId = null): ?Event
    {
        return Event::query()
            ->where('dedup_hash', $dedupHash)
            ->when($excludingSourceId !== null, fn ($query) => $query->where(function ($q) use ($excludingSourceId) {
                $q->whereNull('source_id')->orWhere('source_id', '!=', $excludingSourceId);
            }))
            ->first();
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }
}
