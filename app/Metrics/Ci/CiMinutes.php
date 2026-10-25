<?php

namespace App\Metrics\Ci;

use App\Metrics\Better;
use App\Metrics\Unit;

final class CiMinutes extends CiMetric
{
    public function key(): string
    {
        return 'ciMinutes';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Neutral;
    }

    public function description(): string
    {
        return 'Total wall-clock minutes, start to finish, of every workflow run created in the period, whatever its outcome. Runs longer than 6h15m (never really executed) and excluded workflows are left out.';
    }

    protected function measure(CiWindow $window): int
    {
        return (int) round($window->count('seconds') / 60);
    }
}
