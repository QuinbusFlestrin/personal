<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['canton_id', 'name', 'slug', 'lat', 'lng'])]
class City extends Model
{
    /** @use HasFactory<\Database\Factories\CityFactory> */
    use HasFactory;

    public function canton(): BelongsTo
    {
        return $this->belongsTo(Canton::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }
}
