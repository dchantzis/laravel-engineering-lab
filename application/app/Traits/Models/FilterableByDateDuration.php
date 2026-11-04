<?php

namespace App\Traits\Models;

use App\Traits\FilterableByDateDuration as BaseFilterableByDateDuration;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @method EloquentBuilder|QueryBuilder|static byDuration(Carbon|null $startDate = null, Carbon|null $endDate = null, string|null $dateAttribute = null, string|null $dateFormat = null)
 */
trait FilterableByDateDuration
{
    use BaseFilterableByDateDuration;

    public function scopeByDuration(
        EloquentBuilder|QueryBuilder $query,
        Carbon|CarbonImmutable|null $startDate = null,
        Carbon|CarbonImmutable|null $endDate = null,
        ?string $dateAttribute = null,
        ?string $dateFormat = null // 'Y-m-d H:i:s' or 'U' for Unix Timestamp
    ): EloquentBuilder|QueryBuilder {
        return static::inDateDuration(
            query: $query,
            startDate: $startDate,
            endDate: $endDate,
            dateAttribute: $dateAttribute,
            dateFormat: $dateFormat
        );
    }
}
