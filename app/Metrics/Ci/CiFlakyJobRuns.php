<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiFlakyJobRuns extends FlakyJobMetric
{
    public function key(): string
    {
        return 'ciFlakyJobRuns';
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
        return 'Times a job failed on one attempt of its run and passed on a later one, counted per run and job, over runs in the period. A run where two jobs were flaky counts twice. Excluded workflows are left out, and so is the period while none of its re-runs have had their jobs fetched.';
    }

    protected function measure(CiWindow $window): ?int
    {
        return $window->flakyJobsKnown() ? $window->flakyJobs('runs') : null;
    }
}
