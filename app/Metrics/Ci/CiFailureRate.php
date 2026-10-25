<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\CiFailureRateScope;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class CiFailureRate extends CiMetric
{
    public function key(): string
    {
        return 'ciFailureRate';
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
        $scope = CiFailureRateScope::current();

        return "Failed workflow runs divided by all runs that passed or failed in the period, {$scope->runs()}. Cancelled and skipped runs, and excluded workflows, are left out. Which runs count is set in the report defaults.";
    }

    protected function measure(CiWindow $window): ?float
    {
        return match ($window->failureRateScope) {
            CiFailureRateScope::DefaultBranch => Stats::ratio($window->count('default_branch_failed'), $window->count('default_branch')),
            CiFailureRateScope::AllBranches => Stats::ratio($window->count('failed'), $window->count('decisive')),
        };
    }
}
