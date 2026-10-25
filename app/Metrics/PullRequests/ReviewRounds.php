<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\ReviewFlow;
use App\Metrics\Statistic;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class ReviewRounds extends PullRequestDistribution
{
    public function key(): string
    {
        return 'reviewRounds';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function statistic(): Statistic
    {
        return Statistic::Average;
    }

    public function description(): string
    {
        return 'Average times a reviewer comment or change request handed a pull request back to its author, across pull requests merged in the period that got a human response. Approvals and bots don\'t count.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->flows()
            ->filter(fn (ReviewFlow $flow): bool => $flow->responded)
            ->map(fn (ReviewFlow $flow): int => $flow->rounds);
    }
}
