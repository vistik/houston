<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;

final class Opened extends PullRequestMetric
{
    public function key(): string
    {
        return 'opened';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Count of pull requests opened in the period, drafts included, whatever happened to them since. Bot-authored pull requests are left out unless bots are included.';
    }

    protected function measure(PullRequestWindow $window): int
    {
        return $window->opened->count();
    }
}
