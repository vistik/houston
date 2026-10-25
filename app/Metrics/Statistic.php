<?php

namespace App\Metrics;

/**
 * How a metric sums up its values, like the cycle time of every merged pull request, into one number.
 */
enum Statistic: string
{
    case Median = 'median';

    case Average = 'average';

    /**
     * @param  iterable<int|float|null>  $values
     */
    public function of(iterable $values): ?float
    {
        return match ($this) {
            self::Median => Stats::median($values),
            self::Average => Stats::average($values),
        };
    }
}
