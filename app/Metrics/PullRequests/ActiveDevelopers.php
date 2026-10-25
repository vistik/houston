<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;

final class ActiveDevelopers extends PullRequestMetric
{
    public function key(): string
    {
        return 'activeDevelopers';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Count of distinct people who opened or merged a pull request, or reviewed someone else\'s, in the period. Bots are left out.';
    }

    /**
     * People who opened or merged a pull request, or reviewed someone else's, within a window. Bots don't count,
     * even when their pull requests are included.
     */
    protected function measure(PullRequestWindow $window): int
    {
        return $window->opened->concat($window->merged)
            ->reject(fn (PullRequest $pr): bool => $pr->author === null || $pr->author_is_bot)
            ->pluck('author')
            ->concat($window->reviewers())
            ->unique()
            ->count();
    }
}
