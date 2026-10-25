<?php

namespace App\Metrics\Delivery;

use App\Enums\DeploymentSource;
use App\Metrics\Better;
use App\Metrics\Stats;
use App\Metrics\Unit;
use App\Models\Deployment;

final class DeploymentFailureRate extends DeliveryMetric
{
    public function key(): string
    {
        return 'deploymentFailureRate';
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
        return 'Deployment attempts that failed or errored divided by attempts that succeeded or failed in the period. Only GitHub Deployments report failures, so repositories tracked by releases have no value.';
    }

    protected function measure(DeliveryWindow $window): ?float
    {
        if (! $window->attempts->contains('source', DeploymentSource::Deployments)) {
            return null;
        }

        return Stats::ratio(
            $window->attempts->filter->failed()->count(),
            $window->attempts->filter(fn (Deployment $deployment): bool => $deployment->failed() || $deployment->succeeded())->count(),
        );
    }
}
