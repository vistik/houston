<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class ReviewTime extends PullRequestDistribution
{
    public function key(): string
    {
        return 'reviewTime';
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
        return 'Median working time from the first review or comment by someone other than the author (bots excluded) to merging, across pull requests merged in the period that got one.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->filter->first_response_at->map(fn (PullRequest $pr): int => $window->seconds($pr->first_response_at, $pr->merged_at));
    }
}
