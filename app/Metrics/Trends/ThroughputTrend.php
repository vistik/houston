<?php

namespace App\Metrics\Trends;

use App\Metrics\Trend;
use App\Metrics\Unit;

final class ThroughputTrend implements Trend
{
    public function key(): string
    {
        return 'throughput';
    }

    public function title(): string
    {
        return 'Throughput';
    }

    public function summary(): string
    {
        return 'Pull requests opened and merged';
    }

    public function description(): string
    {
        return 'Count of pull requests merged, into any branch, and of pull requests opened, drafts included, in each bucket. Bot-authored pull requests are left out unless bots are included.';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }
}
