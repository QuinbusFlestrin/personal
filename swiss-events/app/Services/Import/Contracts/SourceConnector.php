<?php

namespace App\Services\Import\Contracts;

use App\Models\Source;
use App\Services\Import\DTO\RawEventDTO;

/**
 * Every source type (RSS, iCal, JSON API, scraper) implements this and only
 * this. Adding a new source of an existing type is a data change (a new
 * `sources` row with its own config); adding a new source *type* means a new
 * class implementing this interface.
 */
interface SourceConnector
{
    /**
     * @return iterable<RawEventDTO>
     */
    public function fetch(Source $source): iterable;
}
