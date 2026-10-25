<?php

namespace App\Metrics\Ci;

use App\Metrics\Metric;
use App\Metrics\Window;

/**
 * A metric of the GitHub Actions workflow runs within a window.
 */
abstract class CiMetric implements Metric
{
    final public function calculate(Window $window): float|int|null
    {
        return $this->measure($window->ci());
    }

    abstract protected function measure(CiWindow $window): float|int|null;
}
