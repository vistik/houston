<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class TimeToApproval extends PullRequestDistribution
{
    public function key(): string
    {
        return 'timeToApproval';
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
        return 'Median working time from a pull request being ready for review (or opened, if never a draft) to its first approval, across pull requests first approved in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->approved->map(fn (PullRequest $pr): int => $window->seconds($pr->readyForReviewAt(), $pr->first_approval_at));
    }
}
