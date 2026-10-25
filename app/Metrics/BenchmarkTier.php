<?php

namespace App\Metrics;

/**
 * How a metric compares to its benchmark, from best to worst.
 */
enum BenchmarkTier: string
{
    case Elite = 'elite';

    case Good = 'good';

    case Medium = 'medium';

    case Low = 'low';
}
