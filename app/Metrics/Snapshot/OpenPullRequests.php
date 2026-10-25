<?php

namespace App\Metrics\Snapshot;

use App\Metrics\Better;
use App\Metrics\Unit;

final class OpenPullRequests extends SnapshotMetric
{
    public function key(): string
    {
        return 'openPullRequests';
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
        return 'Pull requests open right now, drafts included. Not tied to the period.';
    }

    protected function measure(Snapshot $snapshot): int
    {
        return $snapshot->open->count();
    }
}
