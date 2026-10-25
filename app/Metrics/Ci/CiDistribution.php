<?php

namespace App\Metrics\Ci;

use App\Metrics\Distribution;
use App\Metrics\Statistic;
use App\Metrics\Window;
use Illuminate\Support\Collection;

/**
 * A CI metric that sums up one value per run, commit or recovery, by its median unless it says otherwise.
 */
abstract class CiDistribution extends CiMetric implements Distribution
{
    public function statistic(): Statistic
    {
        return Statistic::Median;
    }

    final public function values(Window $window): Collection
    {
        return $this->sample($window->ci())->filter(fn ($value): bool => $value !== null)->values();
    }

    final protected function measure(CiWindow $window): ?float
    {
        return $this->statistic()->of($this->sample($window));
    }

    /**
     * @return Collection<int, covariant int|float|null>
     */
    abstract protected function sample(CiWindow $window): Collection;
}
