<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class ReviewConcentration extends PullRequestMetric
{
    public function key(): string
    {
        return 'reviewConcentration';
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
        return 'Peer reviews by the busiest reviewer divided by all peer reviews submitted in the period. Reviews by the author or by bots are left out.';
    }

    /**
     * Share of peer reviews in the window done by the busiest reviewer. High values mean a bus factor of one.
     */
    protected function measure(PullRequestWindow $window): ?float
    {
        $reviewers = $window->reviewers();

        if ($reviewers->isEmpty()) {
            return null;
        }

        return Stats::ratio((int) $reviewers->countBy()->max(), $reviewers->count());
    }
}
