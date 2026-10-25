<?php

namespace App\Models\Concerns;

use DateTimeImmutable;

/**
 * Parses each immutable date attribute once instead of on every read.
 *
 * Eloquent re-parses a date cast every time the attribute is read, which dominates reports that
 * walk tens of thousands of cached rows. Immutable instances are safe to hand out repeatedly, and
 * the memo is keyed by the raw value, so assigning a new value invalidates it.
 */
trait MemoizesImmutableDates
{
    /** @var array<string, array{0: string, 1: DateTimeImmutable}> */
    private array $parsedDates = [];

    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (! is_string($value)) {
            return parent::castAttribute($key, $value);
        }

        $memo = $this->parsedDates[$key] ?? null;

        if ($memo !== null && $memo[0] === $value) {
            return $memo[1];
        }

        $cast = parent::castAttribute($key, $value);

        if ($cast instanceof DateTimeImmutable) {
            $this->parsedDates[$key] = [$value, $cast];
        }

        return $cast;
    }
}
