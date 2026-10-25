<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;

final class Throughput extends PullRequestMetric
{
    public function key(): string
    {
        return 'throughput';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Higher;
    }

    public function description(): string
    {
        return 'Count of pull requests merged in the period, into any branch. Bot-authored pull requests are left out unless bots are included.';
    }

    protected function measure(PullRequestWindow $window): int
    {
        return $window->merged->count();
    }
}
