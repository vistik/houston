<?php

namespace App\Metrics;

/**
 * Small statistical helpers for the velocity reports.
 */
class Stats
{
    /**
     * Linear-interpolated percentile of a list of numbers, ignoring nulls.
     *
     * @param  iterable<int|float|null>  $values
     */
    public static function percentile(iterable $values, float $percentile): ?float
    {
        $sorted = collect($values)->filter(fn ($value): bool => $value !== null)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $index = ($sorted->count() - 1) * $percentile / 100;
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($index - $lower);
    }

    /**
     * @param  iterable<int|float|null>  $values
     */
    public static function median(iterable $values): ?float
    {
        return self::percentile($values, 50);
    }

    /**
     * Mean of a list of numbers to one decimal, ignoring nulls.
     *
     * @param  iterable<int|float|null>  $values
     */
    public static function average(iterable $values): ?float
    {
        $known = collect($values)->filter(fn ($value): bool => $value !== null);

        return $known->isEmpty() ? null : round((float) $known->avg(), 1);
    }

    public static function ratio(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? $part / $whole : null;
    }
}
