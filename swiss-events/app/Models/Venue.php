<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'venue_type', 'address', 'city_id', 'canton_id',
    'lat', 'lng', 'website', 'image', 'source_id', 'status',
])]
class Venue extends Model
{
    /** @use HasFactory<\Database\Factories\VenueFactory> */
    use HasFactory;

    public const TYPE_MUSEUM = 'museum';

    public const TYPE_PARK = 'park';

    public const TYPE_HISTORICAL_BUILDING = 'historical_building';

    public const TYPE_THEATRE = 'theatre';

    public const TYPE_GENERIC = 'generic';

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function canton(): BelongsTo
    {
        return $this->belongsTo(Canton::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
