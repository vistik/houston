<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Distribution;
use App\Metrics\Statistic;
use App\Metrics\Window;
use Illuminate\Support\Collection;

/**
 * A pull request metric that sums up one value per pull request, by its median unless it says otherwise.
 */
abstract class PullRequestDistribution extends PullRequestMetric implements Distribution
{
    public function statistic(): Statistic
    {
        return Statistic::Median;
    }

    final public function values(Window $window): Collection
    {
        return $this->sample($window->pullRequests())->filter(fn ($value): bool => $value !== null)->values();
    }

    final protected function measure(PullRequestWindow $window): ?float
    {
        return $this->statistic()->of($this->sample($window));
    }

    /**
     * @return Collection<int, covariant int|float|null>
     */
    abstract protected function sample(PullRequestWindow $window): Collection;
}
