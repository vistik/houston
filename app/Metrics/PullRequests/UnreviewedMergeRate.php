<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class UnreviewedMergeRate extends PullRequestMetric
{
    public function key(): string
    {
        return 'unreviewedMergeRate';
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
        return 'Pull requests merged in the period without a submitted review from anyone but the author or a bot, divided by all pull requests merged in the period. Plain comments do not count as a review.';
    }

    protected function measure(PullRequestWindow $window): ?float
    {
        return Stats::ratio($window->merged->whereNull('first_review_at')->count(), $window->merged->count());
    }
}
