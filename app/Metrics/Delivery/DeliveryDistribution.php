<?php

namespace App\Metrics\Delivery;

use App\Metrics\Distribution;
use App\Metrics\Statistic;
use App\Metrics\Window;
use Illuminate\Support\Collection;

/**
 * A delivery metric that sums up one value per shipped pull request or deployment, by its median unless
 * it says otherwise.
 */
abstract class DeliveryDistribution extends DeliveryMetric implements Distribution
{
    public function statistic(): Statistic
    {
        return Statistic::Median;
    }

    final public function values(Window $window): Collection
    {
        $delivery = $window->delivery();

        return $delivery->hasSignal ? $this->sample($delivery)->filter(fn ($value): bool => $value !== null)->values() : collect();
    }

    final protected function measure(DeliveryWindow $window): ?float
    {
        return $this->statistic()->of($this->sample($window));
    }

    /**
     * @return Collection<int, covariant int|float|null>
     */
    abstract protected function sample(DeliveryWindow $window): Collection;
}
