<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiFlakyJobMinutes extends FlakyJobMetric
{
    public function key(): string
    {
        return 'ciFlakyJobMinutes';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Wall-clock minutes of the failed attempts of jobs that then passed when their run was re-run, over runs in the period. Excluded workflows are left out, and so is the period while none of its re-runs have had their jobs fetched.';
    }

    protected function measure(CiWindow $window): ?int
    {
        return $window->flakyJobsKnown() ? (int) round($window->flakyJobs('seconds') / 60) : null;
    }
}
