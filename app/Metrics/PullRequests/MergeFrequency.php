<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;

final class MergeFrequency extends PullRequestMetric
{
    public function key(): string
    {
        return 'mergeFrequency';
    }

    public function unit(): Unit
    {
        return Unit::PerDay;
    }

    public function better(): Better
    {
        return Better::Higher;
    }

    public function description(): string
    {
        return 'Pull requests merged into the default branch in the period, divided by the number of days in the period.';
    }

    protected function measure(PullRequestWindow $window): float
    {
        return round($window->merged->filter($window->intoDefaultBranch(...))->count() / $window->days, 2);
    }
}
