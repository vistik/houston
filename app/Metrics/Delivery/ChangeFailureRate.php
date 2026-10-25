<?php

namespace App\Metrics\Delivery;

use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;

final class ChangeFailureRate extends DeliveryMetric
{
    public function key(): string
    {
        return 'changeFailureRate';
    }

    public function unit(): Unit
    {
        return Unit::Ratio;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Deployments that needed a fix divided by all successful deployments in the period. A deployment needed a fix when the next one on its branch ships a revert, hotfix or rollback pull request.';
    }

    protected function measure(DeliveryWindow $window): ?float
    {
        return Stats::ratio($window->failedChanges()->count(), $window->releases->count());
    }
}
