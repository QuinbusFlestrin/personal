<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for turning a filter param array into an Event
 * query — used by the public /events browse page today, and by the Phase 2
 * digest builder so the two never drift apart.
 *
 * Expected $params keys (all optional): category_ids[], canton_ids[],
 * from (date string), to (date string), search (string).
 */
class EventFilterQuery
{
    /**
     * @param  array<string, mixed>  $params
     */
    public static function apply(Builder $query, array $params): Builder
    {
        return $query
            ->published()
            ->when(
                ! empty($params['category_ids']),
                fn (Builder $q) => $q->whereIn('category_id', $params['category_ids'])
            )
            ->when(
                ! empty($params['canton_ids']),
                fn (Builder $q) => $q->whereIn('canton_id', $params['canton_ids'])
            )
            ->when(
                ! empty($params['from']),
                fn (Builder $q) => $q->where('starts_at', '>=', $params['from'])
            )
            ->when(
                ! empty($params['to']),
                fn (Builder $q) => $q->where('starts_at', '<=', $params['to'])
            )
            ->when(
                ! empty($params['search']),
                fn (Builder $q) => $q->where(function (Builder $q) use ($params) {
                    $q->where('title', 'like', "%{$params['search']}%")
                        ->orWhere('description', 'like', "%{$params['search']}%");
                })
            )
            ->when(
                empty($params['from']) && empty($params['to']),
                fn (Builder $q) => $q->upcoming()
            );
    }
}
