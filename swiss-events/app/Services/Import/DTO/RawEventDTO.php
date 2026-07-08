<?php

namespace App\Services\Import\DTO;

use Carbon\CarbonImmutable;

/**
 * Normalized shape every connector must produce, regardless of the
 * source format (RSS/iCal/JSON/scraper). ImportRunner only ever deals
 * with this DTO, never with source-specific data structures.
 */
final class RawEventDTO
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly CarbonImmutable $startsAt,
        public readonly ?CarbonImmutable $endsAt,
        public readonly ?string $venueName = null,
        public readonly ?string $venueAddress = null,
        public readonly ?string $categoryHint = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $image = null,
        public readonly ?string $priceInfo = null,
    ) {}
}
