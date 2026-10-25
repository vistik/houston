<?php

namespace App\Metrics\Delivery;

use App\Metrics\Better;
use App\Metrics\Unit;

final class DeployFrequency extends DeliveryMetric
{
    public function key(): string
    {
        return 'deployFrequency';
    }

    public function unit(): Unit
    {
        return Unit::PerDay;
    }

    public function better(): Better
    {
        return Better::Higher;
    }

    public function description(): string
    {
        return 'Successful deployments to production (or published releases) in the period, divided by the number of days in the period.';
    }

    protected function measure(DeliveryWindow $window): float
    {
        return round($window->releases->count() / max(1, $window->days), 2);
    }
}
