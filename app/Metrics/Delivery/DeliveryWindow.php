<?php

namespace App\Metrics\Delivery;

use App\Metrics\WorkingTime;
use App\Models\Deployment;
use App\Models\PullRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Deployments within a window.
 *
 * A release is a successful deployment with the pull requests it shipped: everything merged into its
 * branch since the deployment before it. A release is a failed change when the next release ships a
 * remediation (a revert, hotfix or rollback), and restored when that next release went out.
 *
 * @phpstan-type Release array{deployment: Deployment, shipped: Collection<int, PullRequest>, failedChange: bool, restoredAt: CarbonImmutable|null}
 */
final readonly class DeliveryWindow
{
    /**
     * @param  Collection<int, Release>  $releases  Successful deployments within the window, except the very first one on record.
     * @param  Collection<int, Deployment>  $attempts  Every deployment attempt within the window, successful or not.
     * @param  bool  $hasSignal  Whether the repository has any deployments at all.
     * @param  int  $days  How long the window is.
     */
    public function __construct(
        public Collection $releases,
        public Collection $attempts,
        public bool $hasSignal,
        public int $days,
        public WorkingTime $time,
    ) {}

    /**
     * @return Collection<int, Release>
     */
    public function failedChanges(): Collection
    {
        return $this->releases->where('failedChange', true);
    }
}
