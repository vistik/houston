<?php

namespace App\Metrics\PullRequests;

use App\Enums\ReviewState;
use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;
use App\Models\PullRequest;

final class ChangesRequestedRate extends PullRequestMetric
{
    public function key(): string
    {
        return 'changesRequestedRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Merged pull requests that received at least one "changes requested" review, divided by all pull requests merged in the period.';
    }

    protected function measure(PullRequestWindow $window): ?float
    {
        return Stats::ratio(
            $window->withTimelines($window->merged)
                ->filter(fn (PullRequest $pr): bool => in_array(ReviewState::ChangesRequested, array_column($window->timeline($pr)->reviews, 'state'), true))
                ->count(),
            $window->merged->count(),
        );
    }
}
