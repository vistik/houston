<?php

namespace App\Metrics;

/**
 * Where a standard benchmark comes from.
 */
enum BenchmarkSource: string
{
    /** The performance clusters of the DORA State of DevOps research. */
    case Dora = 'DORA';

    /** LinearB's engineering benchmarks. */
    case LinearB = 'LinearB';

    /** A suggested target, for metrics without a published benchmark. */
    case Suggested = 'Suggested';
}
