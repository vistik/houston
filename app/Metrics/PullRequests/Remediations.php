<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Unit;

final class Remediations extends PullRequestMetric
{
    public function key(): string
    {
        return 'remediations';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Count of pull requests merged in the period whose title, branch name or a label matches the remediation pattern, by default starting with revert, hotfix or rollback.';
    }

    protected function measure(PullRequestWindow $window): int
    {
        return $window->merged->filter->isRemediation()->count();
    }
}
