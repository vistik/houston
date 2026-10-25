<?php

namespace App\Metrics\Trends;

use App\Metrics\Trend;
use App\Metrics\Unit;

final class TimeToMergeTrend implements Trend
{
    public function key(): string
    {
        return 'timeToMerge';
    }

    public function title(): string
    {
        return 'Time to merge';
    }

    public function summary(): string
    {
        return 'Median for PRs merged in each bucket';
    }

    public function description(): string
    {
        return 'Median working time from opening a pull request to merging it, across pull requests merged in each bucket.';
    }

    public function unit(): Unit
    {
        return Unit::Duration;
    }
}
