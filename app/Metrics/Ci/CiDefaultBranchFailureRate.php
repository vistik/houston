<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class CiDefaultBranchFailureRate extends CiMetric
{
    public function key(): string
    {
        return 'ciDefaultBranchFailureRate';
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
        return 'Failed workflow runs divided by all runs that passed or failed in the period, on the default branch outside pull requests. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return Stats::ratio($window->count('default_branch_failed'), $window->count('default_branch'));
    }
}
