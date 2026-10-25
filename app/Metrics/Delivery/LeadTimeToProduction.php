<?php

namespace App\Metrics\Delivery;

use App\Metrics\Better;
use App\Metrics\Unit;
use App\Models\PullRequest;
use Illuminate\Support\Collection;

final class LeadTimeToProduction extends DeliveryDistribution
{
    public function key(): string
    {
        return 'leadTimeToProduction';
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
        return 'Median working time from a pull request\'s first commit (or opening, without one) to the deployment that shipped it, across pull requests shipped by deployments in the period.';
    }

    protected function sample(DeliveryWindow $window): Collection
    {
        return $window->releases->flatMap(fn (array $release): Collection => $release['shipped']->map(
            fn (PullRequest $pr): int => $window->time->between($pr->first_commit_at ?? $pr->opened_at, $release['deployment']->deployed_at),
        ));
    }
}
