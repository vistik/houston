<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class CiPullRequestFailureRate extends CiMetric
{
    public function key(): string
    {
        return 'ciPullRequestFailureRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Failed or timed-out workflow runs divided by all runs that passed or failed in the period, for pull request events only. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return Stats::ratio($window->count('pull_request_failed'), $window->count('pull_request'));
    }
}
