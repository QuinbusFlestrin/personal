<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'filter_params', 'frequency', 'channel', 'is_active', 'last_notified_at'])]
class SavedFilter extends Model
{
    /** @use HasFactory<\Database\Factories\SavedFilterFactory> */
    use HasFactory;

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filter_params' => 'array',
            'is_active' => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
