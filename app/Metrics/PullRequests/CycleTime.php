<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class CycleTime extends PullRequestDistribution
{
    public function key(): string
    {
        return 'cycleTime';
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
        return 'Median working time from opening a pull request to merging it, across pull requests merged in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->map(fn (PullRequest $pr): int => $window->seconds($pr->opened_at, $pr->merged_at));
    }
}
