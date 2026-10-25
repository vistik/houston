<?php

namespace App\Metrics\Delivery;

use App\Metrics\Better;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class TimeToRestore extends DeliveryDistribution
{
    public function key(): string
    {
        return 'timeToRestore';
    }

    public function unit(): Unit
    {
        return Unit::Duration;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Median working time from a deployment that needed a fix to the next deployment of its branch, which shipped the revert, hotfix or rollback, across such deployments in the period.';
    }

    protected function sample(DeliveryWindow $window): Collection
    {
        return $window->failedChanges()->map(
            fn (array $release): ?int => $release['restoredAt'] ? $window->time->between($release['deployment']->deployed_at, $release['restoredAt']) : null,
        );
    }
}
