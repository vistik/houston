<?php

namespace App\Metrics;

/**
 * What a metric's value measures.
 */
enum Unit: string
{
    case Count = 'count';

    /** Seconds; working time for durations that measure people, wall-clock for CI. */
    case Duration = 'duration';

    /** A share between 0 and 1. */
    case Ratio = 'ratio';

    /** Changed lines, added plus deleted. */
    case Lines = 'lines';

    case PerDay = 'perDay';
}
