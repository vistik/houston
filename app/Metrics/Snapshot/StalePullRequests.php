<?php

namespace App\Metrics\Snapshot;

use App\Metrics\Better;
use App\Metrics\Unit;

final class StalePullRequests extends SnapshotMetric
{
    public function key(): string
    {
        return 'stalePullRequests';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }

    public function better(): Better
    {
        return Better::Lower;
    }

    public function description(): string
    {
        return 'Open pull requests, drafts included, with no human activity (opening, marking ready, reviews, comments, pushes) for 7 days by default. Bot activity is ignored. Not tied to the period.';
    }

    protected function measure(Snapshot $snapshot): int
    {
        return $snapshot->stale()->count();
    }
}
