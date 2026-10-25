<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class LeadTime extends PullRequestDistribution
{
    public function key(): string
    {
        return 'leadTime';
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
        return 'Median working time from a pull request\'s first commit to its merge, across pull requests merged in the period. Pull requests without a known first commit are left out.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->filter->first_commit_at->map(fn (PullRequest $pr): int => $window->seconds($pr->first_commit_at, $pr->merged_at));
    }
}
