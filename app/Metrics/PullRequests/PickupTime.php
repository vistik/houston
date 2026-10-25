<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class PickupTime extends PullRequestDistribution
{
    public function key(): string
    {
        return 'pickupTime';
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
        return 'Median working time from a pull request being ready for review to the first review or comment by someone other than the author or a bot, across pull requests first responded to in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->pickedUp->map(fn (PullRequest $pr): int => $window->seconds($pr->readyForReviewAt(), $pr->first_response_at));
    }
}
