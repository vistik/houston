<?php

namespace App\Metrics\Trends;

use App\Metrics\Trend;
use App\Metrics\Unit;

final class OpenPullRequestsTrend implements Trend
{
    public function key(): string
    {
        return 'openPullRequests';
    }

    public function title(): string
    {
        return 'Open pull requests';
    }

    public function summary(): string
    {
        return 'Pull requests open at the end of each bucket, ready for review and drafts';
    }

    public function description(): string
    {
        return 'Count of pull requests not yet merged or closed at the end of each bucket, or right now for the bucket still in progress, split by whether they were ready for review or still drafts. Each pull request counts as a draft while it was one, following when it was marked ready for review and converted back to draft. Bot-authored pull requests are left out unless bots are included.';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }
}
