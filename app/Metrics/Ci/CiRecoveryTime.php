<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;
use Illuminate\Support\Collection;

final class CiRecoveryTime extends CiDistribution
{
    public function key(): string
    {
        return 'ciRecoveryTime';
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
        return 'Median working time from a workflow\'s first failing run on the default branch to the pass that fixed it, for recoveries in the period. Pull request runs and excluded workflows are left out.';
    }

    protected function sample(CiWindow $window): Collection
    {
        return $window->recoveryTimes();
    }
}
