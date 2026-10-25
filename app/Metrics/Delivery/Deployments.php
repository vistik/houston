<?php

namespace App\Metrics\Delivery;

use App\Metrics\Better;
use App\Metrics\Unit;

final class Deployments extends DeliveryMetric
{
    public function key(): string
    {
        return 'deployments';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Higher;
    }

    public function description(): string
    {
        return 'Successful deployments to production (or published releases) in the period.';
    }

    protected function measure(DeliveryWindow $window): int
    {
        return $window->releases->count();
    }
}
