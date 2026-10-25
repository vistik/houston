<?php

namespace App\Metrics\Trends;

use App\Metrics\Trend;
use App\Metrics\Unit;

final class PickupTimeTrend implements Trend
{
    public function key(): string
    {
        return 'pickupTime';
    }

    public function title(): string
    {
        return 'Pickup time';
    }

    public function summary(): string
    {
        return 'Median wait for a first response from someone else';
    }

    public function description(): string
    {
        return 'Median working time from a pull request being ready for review to the first review or comment by someone other than the author or a bot, across pull requests first responded to in each bucket.';
    }

    public function unit(): Unit
    {
        return Unit::Duration;
    }
}
