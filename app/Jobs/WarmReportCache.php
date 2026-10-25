<?php

namespace App\Jobs;

use App\Enums\SystemEventType;
use App\Models\Repository;
use App\Models\SystemEvent;
use App\Reports\ReportCache;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Computes the reports people are likely to open after a sync, on its own so the sync can finish
 * without waiting for them. One waits in the queue per repository at a time; a sync that finishes
 * while one is running queues another, so the cache catches up with the newest data.
 */
class WarmReportCache implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public Repository $repository) {}

    public function uniqueId(): string
    {
        return (string) $this->repository->id;
    }

    public function handle(ReportCache $reports): void
    {
        $startedAt = microtime(true);

        $reports->warm($this->repository);

        SystemEvent::record(SystemEventType::CacheWarmed, "Warmed the report cache of {$this->repository->fullName()}", $this->repository, [
            'milliseconds' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
