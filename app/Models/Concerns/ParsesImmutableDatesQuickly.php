<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Parses immutable date attributes straight from the database's format.
 *
 * Eloquent re-parses a date cast every time the attribute is read, which dominates reports that
 * walk tens of thousands of cached rows. Its checks around the parse cost three times the parse
 * itself, so a value in the database's format skips them. The dates are not kept: at about 1.5 KB
 * each, keeping every date of a busy repository's pull requests runs a worker out of memory.
 */
trait ParsesImmutableDatesQuickly
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_string($value) && ($this->casts[$key] ?? null) === 'immutable_datetime') {
            try {
                return CarbonImmutable::createFromFormat($this->getDateFormat(), $value) ?: parent::castAttribute($key, $value);
            } catch (InvalidFormatException) {
                return parent::castAttribute($key, $value);
            }
        }

        return parent::castAttribute($key, $value);
    }
}
