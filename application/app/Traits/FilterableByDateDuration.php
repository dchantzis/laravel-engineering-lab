<?php

namespace App\Traits;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\Constants\Format;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait FilterableByDateDuration
{
    /**
     * @param  string|null  $dateFormat  'Y-m-d H:i:s' or 'U' for Unix Timestamp
     */
    public static function inDateDuration(
        EloquentBuilder|QueryBuilder $query,
        Carbon|CarbonImmutable|null $startDate = null,
        Carbon|CarbonImmutable|null $endDate = null,
        ?string $dateAttribute = null,
        ?string $dateFormat = null
    ): EloquentBuilder|QueryBuilder {
        if (! $dateAttribute) {
            $dateAttribute = 'created_at';
        }

        if (is_null($startDate) && is_null($endDate)) {
            return $query;
        }

        if (! $dateFormat) {
            $dateFormat = Format::DEFAULT_TO_STRING_FORMAT;
        }

        // Only start date is specified
        if (! is_null($startDate) && is_null($endDate)) {
            return $query->where($dateAttribute, '>=', $startDate->format($dateFormat));
        }

        // Only end date is specified
        if (is_null($startDate) && ! is_null($endDate)) {
            return $query->where($dateAttribute, '<=', $endDate->format($dateFormat));
        }

        return $query
            ->whereBetween($dateAttribute, [$startDate->format($dateFormat), $endDate->format($dateFormat)]);
    }
}
