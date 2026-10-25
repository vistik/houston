<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class MergeRate extends PullRequestMetric
{
    public function key(): string
    {
        return 'mergeRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Higher;
    }

    public function description(): string
    {
        return 'Pull requests merged in the period divided by those merged plus those closed without merging in the period.';
    }

    protected function measure(PullRequestWindow $window): ?float
    {
        return Stats::ratio($window->merged->count(), $window->merged->count() + $window->abandoned->count());
    }
}
