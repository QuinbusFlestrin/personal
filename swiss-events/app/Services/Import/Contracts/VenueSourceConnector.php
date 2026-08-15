<?php

namespace App\Services\Import\Contracts;

use App\Models\Source;
use App\Services\Import\DTO\RawVenueDTO;

/**
 * Counterpart to SourceConnector for sources that publish *places* rather than
 * dated events (tourist attractions, museums, parks).
 *
 * A source declares which of the two it is with `config.content` — "events"
 * (the default) or "venues" — so the same connector machinery, admin resource
 * and import-run bookkeeping serve both.
 */
interface VenueSourceConnector
{
    /**
     * @return iterable<RawVenueDTO>
     */
    public function fetchVenues(Source $source): iterable;
}
