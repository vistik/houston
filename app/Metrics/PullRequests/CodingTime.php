<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class CodingTime extends PullRequestDistribution
{
    public function key(): string
    {
        return 'codingTime';
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
        return 'Median working time from the first commit to opening the pull request, across pull requests merged in the period that have a known first commit.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->filter->first_commit_at->map(fn (PullRequest $pr): int => $window->seconds($pr->first_commit_at, $pr->opened_at));
    }
}
