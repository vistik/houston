<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class CiRerunRate extends CiMetric
{
    public function key(): string
    {
        return 'ciRerunRate';
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
        return 'Workflow runs that were a second or later attempt divided by all runs that passed or failed in the period. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return Stats::ratio($window->count('reruns'), $window->count('decisive'));
    }
}
