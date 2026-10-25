<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class DefaultBranchRedShare extends CiMetric
{
    public function key(): string
    {
        return 'defaultBranchRedShare';
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
        return 'Working time in the period during which any workflow on the default branch was red, divided by all working time in the period. Red runs from a failed run until the next passing run finishes.';
    }

    protected function measure(CiWindow $window): ?float
    {
        return $window->redShare();
    }
}
