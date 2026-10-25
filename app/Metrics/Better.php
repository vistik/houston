<?php

namespace App\Metrics;

/**
 * Which direction of a metric is an improvement.
 */
enum Better: string
{
    case Higher = 'higher';

    case Lower = 'lower';

    /** Neither direction is better in itself, e.g. the number of pull requests opened. */
    case Neutral = 'neutral';
}
