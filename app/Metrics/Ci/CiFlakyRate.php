<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiFlakyRate extends CiMetric
{
    public function key(): string
    {
        return 'ciFlakyRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Commits whose workflow both failed and passed, or passed only on a rerun, divided by all commits, counted per workflow and commit over runs in the period. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return $window->flakyRate();
    }
}
