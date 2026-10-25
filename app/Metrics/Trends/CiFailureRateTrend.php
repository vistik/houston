<?php

namespace App\Metrics\Trends;

use App\Metrics\CiFailureRateScope;
use App\Metrics\Trend;
use App\Metrics\Unit;

final class CiFailureRateTrend implements Trend
{
    public function key(): string
    {
        return 'ciFailureRate';
    }

    public function title(): string
    {
        return 'CI failure rate';
    }

    public function summary(): string
    {
        return match (CiFailureRateScope::current()) {
            CiFailureRateScope::DefaultBranch => 'Share of finished workflow runs on the default branch that failed',
            CiFailureRateScope::AllBranches => 'Share of finished workflow runs that failed',
        };
    }

    public function description(): string
    {
        $scope = CiFailureRateScope::current();

        return "Failed workflow runs divided by all runs that passed or failed in each bucket, {$scope->runs()}. Cancelled and skipped runs, and excluded workflows, are left out. Which runs count is set in the report defaults.";
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }
}
