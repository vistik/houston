<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class CiDuration extends CiDistribution
{
    public function key(): string
    {
        return 'ciDuration';
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
        return 'Median wall-clock time per commit, taken from its slowest workflow run that passed or failed in the period. Cancelled and skipped runs, and excluded workflows, are left out.';
    }

    protected function sample(CiWindow $window): Collection
    {
        return $window->commitDurations();
    }
}
