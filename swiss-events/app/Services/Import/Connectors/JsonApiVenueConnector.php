<?php

namespace App\Services\Import\Connectors;

use App\Models\Source;
use App\Services\Import\Connectors\Concerns\FetchesPaginatedJson;
use App\Services\Import\Contracts\VenueSourceConnector;
use App\Services\Import\DTO\RawVenueDTO;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Places variant of JsonApiConnector — same HTTP, auth and pagination handling
 * (see FetchesPaginatedJson), different field mapping and output DTO.
 *
 * Used for sources like Switzerland Tourism's /attractions, which publish
 * museums, landmarks and natural sights rather than dated events.
 *
 * Expected config shape: as JsonApiConnector, plus
 *   content: "venues"
 *   field_map: {
 *     external_id, name, description, address, location_hint,
 *     lat, lng, website, image, venue_type
 *   }
 */
class JsonApiVenueConnector implements VenueSourceConnector
{
    use FetchesPaginatedJson;

    public function fetchVenues(Source $source): iterable
    {
        $fieldMap = $source->config['field_map'] ?? [];

        if (! isset($fieldMap['name'])) {
            throw new RuntimeException("Source #{$source->id} ({$source->name}) config.field_map must at least map name");
        }

        foreach ($this->jsonItems($source) as $item) {
            $dto = $this->toDto($item, $fieldMap);

            if ($dto !== null) {
                yield $dto;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMap
     */
    private function toDto(array $item, array $fieldMap): ?RawVenueDTO
    {
        $name = $this->string(Arr::get($item, $fieldMap['name']));

        if ($name === null) {
            return null;
        }

        return new RawVenueDTO(
            externalId: isset($fieldMap['external_id']) ? $this->string(Arr::get($item, $fieldMap['external_id'])) : null,
            name: $name,
            description: isset($fieldMap['description']) ? $this->string(Arr::get($item, $fieldMap['description'])) : null,
            address: isset($fieldMap['address']) ? $this->string(Arr::get($item, $fieldMap['address'])) : null,
            locationHint: isset($fieldMap['location_hint']) ? $this->string(Arr::get($item, $fieldMap['location_hint'])) : null,
            lat: isset($fieldMap['lat']) ? $this->float(Arr::get($item, $fieldMap['lat'])) : null,
            lng: isset($fieldMap['lng']) ? $this->float(Arr::get($item, $fieldMap['lng'])) : null,
            website: isset($fieldMap['website']) ? $this->string(Arr::get($item, $fieldMap['website'])) : null,
            image: isset($fieldMap['image']) ? $this->string(Arr::get($item, $fieldMap['image'])) : null,
            venueType: $this->venueType($item, $fieldMap),
        );
    }

    /**
     * The venue type is sometimes a plain field and sometimes buried in a list
     * of typed classifications (Switzerland Tourism publishes e.g.
     * `classification[] {name: "buildingstype", values: [{name: "bridge"}]}`),
     * so a dot path alone can't reach it.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $fieldMap
     */
    private function venueType(array $item, array $fieldMap): ?string
    {
        if (isset($fieldMap['venue_type'])) {
            $direct = $this->string(Arr::get($item, $fieldMap['venue_type']));

            if ($direct !== null) {
                return $direct;
            }
        }

        $wanted = (array) ($fieldMap['venue_type_classifications'] ?? []);

        if ($wanted === []) {
            return null;
        }

        $classifications = Arr::get($item, $fieldMap['classification_path'] ?? 'classification', []);

        if (! is_array($classifications)) {
            return null;
        }

        // Ordered by preference, so a more specific classification wins.
        foreach ($wanted as $name) {
            foreach ($classifications as $classification) {
                if (! is_array($classification) || ($classification['name'] ?? null) !== $name) {
                    continue;
                }

                $value = $classification['values'][0] ?? null;

                if (is_array($value)) {
                    $type = $this->string($value['title'] ?? $value['name'] ?? null);

                    if ($type !== null) {
                        return $type;
                    }
                }
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        if (is_array($value)) {
            // Repeated fields arrive as lists; take the first usable entry.
            $value = $value[0] ?? null;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
