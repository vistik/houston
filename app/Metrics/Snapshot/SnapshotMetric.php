<?php

namespace App\Metrics\Snapshot;

use App\Metrics\Metric;
use App\Metrics\Window;

/**
 * A metric of the repository right now. It has no previous value to compare with.
 */
abstract class SnapshotMetric implements Metric
{
    final public function calculate(Window $window): float|int|null
    {
        return $this->measure($window->snapshot());
    }

    abstract protected function measure(Snapshot $snapshot): float|int|null;
}
