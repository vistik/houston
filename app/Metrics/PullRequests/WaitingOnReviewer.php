<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\ReviewFlow;
use App\Metrics\ReviewStage;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class WaitingOnReviewer extends PullRequestDistribution
{
    public function key(): string
    {
        return 'waitingOnReviewer';
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
        return 'Median working time a pull request waited on reviewers after being ready for review, for the first response and after each author reply or push, across pull requests merged in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->flows()->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Pickup) + $flow->secondsIn(ReviewStage::Reviewer));
    }
}
