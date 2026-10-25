<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiRuns extends CiMetric
{
    public function key(): string
    {
        return 'ciRuns';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Count of workflow runs created in the period, on any branch and with any outcome, including cancelled and skipped runs. Excluded workflows are left out.';
    }

    protected function measure(CiWindow $window): int
    {
        return $window->count('runs');
    }
}
