<?php

namespace App\Services\Import\DTO;

/**
 * Normalized shape for imported *places* — museums, attractions, parks,
 * theatres — as opposed to the dated happenings RawEventDTO carries.
 *
 * Venues are the "places" half of the site: a venue with no events is simply a
 * place page, which is why they're worth importing on their own rather than
 * only appearing as a by-product of an event import.
 */
final class RawVenueDTO
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $address = null,
        /** Free text (city, region, canton…) used to place the venue. */
        public readonly ?string $locationHint = null,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $website = null,
        public readonly ?string $image = null,
        /** Maps onto Venue::TYPE_* once resolved; null falls back to generic. */
        public readonly ?string $venueType = null,
    ) {}
}
