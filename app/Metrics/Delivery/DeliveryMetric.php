<?php

namespace App\Metrics\Delivery;

use App\Metrics\Metric;
use App\Metrics\Window;

/**
 * A DORA delivery metric of the deployments within a window. Repositories without any deployments
 * have no value at all, rather than zero.
 */
abstract class DeliveryMetric implements Metric
{
    final public function calculate(Window $window): float|int|null
    {
        $delivery = $window->delivery();

        return $delivery->hasSignal ? $this->measure($delivery) : null;
    }

    abstract protected function measure(DeliveryWindow $window): float|int|null;
}
