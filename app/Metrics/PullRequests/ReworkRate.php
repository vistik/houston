<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\ReviewFlow;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class ReworkRate extends PullRequestMetric
{
    public function key(): string
    {
        return 'reworkRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Merged pull requests with a push after their first peer review or comment, divided by merged pull requests that got one once ready for review. Pushes in the two minutes before merging are ignored.';
    }

    protected function measure(PullRequestWindow $window): ?float
    {
        $reviewed = $window->flows()->filter(fn (ReviewFlow $flow): bool => $flow->responded);

        return Stats::ratio($reviewed->filter(fn (ReviewFlow $flow): bool => $flow->reworkPushes > 0)->count(), $reviewed->count());
    }
}
