<?php

namespace App\Metrics;

use Illuminate\Support\Collection;

/**
 * A metric that sums up one value per item, like the cycle time of each merged pull request. Its
 * reports carry both the median and the average of those values next to the headline statistic.
 */
interface Distribution extends Metric
{
    /**
     * The statistic the metric's value is.
     */
    public function statistic(): Statistic;

    /**
     * The values the metric sums up over a window, without the ones that are unknown.
     *
     * @return Collection<int, int|float>
     */
    public function values(Window $window): Collection;
}
