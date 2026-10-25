<?php

namespace App\Metrics;

use App\Metrics\Ci\CiWindow;
use App\Metrics\Delivery\DeliveryWindow;
use App\Metrics\PullRequests\PullRequestWindow;
use App\Metrics\Snapshot\Snapshot;
use Closure;
use LogicException;

/**
 * Everything a metric can be calculated from over one window of time. Each part is only built when
 * a metric asks for it.
 */
final class Window
{
    private ?PullRequestWindow $pullRequests = null;

    private ?CiWindow $ci = null;

    private ?DeliveryWindow $delivery = null;

    private ?Snapshot $snapshot = null;

    /**
     * @param  (Closure(): PullRequestWindow)|null  $makePullRequests
     * @param  (Closure(): CiWindow)|null  $makeCi
     * @param  (Closure(): DeliveryWindow)|null  $makeDelivery
     * @param  (Closure(): Snapshot)|null  $makeSnapshot
     */
    public function __construct(
        private ?Closure $makePullRequests = null,
        private ?Closure $makeCi = null,
        private ?Closure $makeDelivery = null,
        private ?Closure $makeSnapshot = null,
    ) {}

    public function pullRequests(): PullRequestWindow
    {
        return $this->pullRequests ??= ($this->makePullRequests ?? throw new LogicException('This window has no pull requests.'))();
    }

    public function ci(): CiWindow
    {
        return $this->ci ??= ($this->makeCi ?? throw new LogicException('This window has no CI runs.'))();
    }

    public function delivery(): DeliveryWindow
    {
        return $this->delivery ??= ($this->makeDelivery ?? throw new LogicException('This window has no deployments.'))();
    }

    public function snapshot(): Snapshot
    {
        return $this->snapshot ??= ($this->makeSnapshot ?? throw new LogicException('This window has no snapshot.'))();
    }
}
