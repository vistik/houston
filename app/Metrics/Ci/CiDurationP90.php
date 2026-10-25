<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class CiDurationP90 extends CiMetric
{
    public function key(): string
    {
        return 'ciDurationP90';
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
        return '90th percentile of wall-clock CI time per commit, taking each commit\'s slowest run that passed or failed in the period. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return Stats::percentile($window->commitDurations(), 90);
    }
}
