<?php

namespace App\Metrics\PullRequests;

use App\Metrics\Metric;
use App\Metrics\Window;

/**
 * A metric of the pull requests that moved within a window. These are also charted per trend bucket.
 */
abstract class PullRequestMetric implements Metric
{
    final public function calculate(Window $window): float|int|null
    {
        return $this->measure($window->pullRequests());
    }

    abstract protected function measure(PullRequestWindow $window): float|int|null;
}
