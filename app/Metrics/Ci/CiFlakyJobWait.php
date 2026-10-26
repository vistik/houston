<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiFlakyJobWait extends FlakyJobMetric
{
    public function key(): string
    {
        return 'ciFlakyJobWait';
    }

    public function unit(): Unit
    {
        return Unit::Duration;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Working time spent waiting on jobs that failed and then passed when their run was re-run: from the failed attempt finishing to the re-run passing, counted once per run, over runs in the period. Excluded workflows are left out, and so is the period while none of its re-runs have had their jobs fetched.';
    }

    protected function measure(CiWindow $window): ?int
    {
        return $window->flakyJobsKnown() ? $window->flakyJobs('waitSeconds') : null;
    }
}
