<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class MergeDelay extends PullRequestDistribution
{
    public function key(): string
    {
        return 'mergeDelay';
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
        return 'Median working time from the first approval by someone other than the author (bots excluded) to merging, across approved pull requests merged in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->filter->first_approval_at->map(fn (PullRequest $pr): int => $window->seconds($pr->first_approval_at, $pr->merged_at));
    }
}
