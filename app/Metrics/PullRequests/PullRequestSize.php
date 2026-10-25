<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class PullRequestSize extends PullRequestDistribution
{
    public function key(): string
    {
        return 'pullRequestSize';
    }

    public function unit(): Unit
    {
        return Unit::Lines;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Median number of changed lines (lines added plus lines deleted) per pull request, across pull requests merged in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->map(fn (PullRequest $pr): int => $pr->size());
    }
}
