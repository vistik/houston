<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;
use App\Models\PullRequest;

final class CycleTimeP90 extends PullRequestMetric
{
    public function key(): string
    {
        return 'cycleTimeP90';
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
        return '90th percentile of working time from opening a pull request to merging it, across pull requests merged in the period.';
    }

    protected function measure(PullRequestWindow $window): ?float
    {
        return Stats::percentile($window->merged->map(fn (PullRequest $pr): int => $window->seconds($pr->opened_at, $pr->merged_at)), 90);
    }
}
