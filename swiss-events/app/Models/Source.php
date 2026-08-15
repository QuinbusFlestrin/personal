<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'config', 'trust_level', 'is_active', 'last_run_at', 'last_run_status'])]
class Source extends Model
{
    /** @use HasFactory<\Database\Factories\SourceFactory> */
    use HasFactory;

    public const TYPE_RSS = 'rss';

    public const TYPE_ICAL = 'ical';

    public const TYPE_JSON_API = 'json_api';

    public const TYPE_SCRAPER = 'scraper';

    public const TYPE_JSON_LD = 'json_ld';

    public const TYPE_MANUAL = 'manual';

    public const TRUST_TRUSTED = 'trusted';

    public const TRUST_UNTRUSTED = 'untrusted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }

    public function isTrusted(): bool
    {
        return $this->trust_level === self::TRUST_TRUSTED;
    }
}
