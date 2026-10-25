<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\ReviewFlow;
use App\Metrics\ReviewStage;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class WaitingOnAuthor extends PullRequestDistribution
{
    public function key(): string
    {
        return 'waitingOnAuthor';
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
        return 'Median working time the author spent responding to review, from a reviewer\'s comment or change request until their next push or reply, across pull requests merged in the period that got a review.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->flows()
            ->filter(fn (ReviewFlow $flow): bool => $flow->responded)
            ->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Author));
    }
}
