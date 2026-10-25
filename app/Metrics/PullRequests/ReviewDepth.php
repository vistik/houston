<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Better;
use App\Metrics\Statistic;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class ReviewDepth extends PullRequestDistribution
{
    public function key(): string
    {
        return 'reviewDepth';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function statistic(): Statistic
    {
        return Statistic::Average;
    }

    public function description(): string
    {
        return 'Average number of comments per pull request, counting review comments and conversation comments, across pull requests merged in the period.';
    }

    protected function sample(PullRequestWindow $window): Collection
    {
        return $window->merged->map(fn (PullRequest $pr): int => $pr->review_comments_count + $pr->comments_count);
    }
}
