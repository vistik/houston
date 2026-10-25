<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class CiQueueTime extends CiDistribution
{
    public function key(): string
    {
        return 'ciQueueTime';
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
        return 'Median wall-clock time from a workflow run being created to it starting, for first attempts that passed or failed in the period. Re-runs, cancelled and skipped runs, and excluded workflows are left out.';
    }

    protected function sample(CiWindow $window): Collection
    {
        return $window->durations('queue_seconds');
    }
}
