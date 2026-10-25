<?php

namespace App\Metrics;

/**
 * How charts group a period into buckets.
 */
enum Granularity: string
{
    case Day = 'day';

    case Week = 'week';

    /** Rolling 30-day buckets, like the periods themselves. */
    case Month = 'month';

    /**
     * The number of days in each bucket.
     */
    public function days(): int
    {
        return match ($this) {
            self::Day => 1,
            self::Week => 7,
            self::Month => 30,
        };
    }
}
