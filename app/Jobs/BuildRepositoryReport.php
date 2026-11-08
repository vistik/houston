<?php

namespace App\Jobs;

use App\Metrics\Period;
use App\Models\Repository;
use App\Reports\ReportCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds a repository's full report for a page waiting on it, so the page doesn't build it in the
 * request. One is queued or running per report at a time, however often the page asks: the page
 * polls until the report is cached, which only happens once the build finishes, or until it fails.
 */
class BuildRepositoryReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /** Frees the lock if a worker dies mid-build, so the page can ask again. */
    public int $uniqueFor = 900;

    public function __construct(public Repository $repository, public Period $period, public bool $includeBots = false) {}

    public function uniqueId(): string
    {
        return "{$this->repository->id}:{$this->period->key()}:{$this->period->timezone}:".(int) $this->includeBots;
    }

    public function handle(ReportCache $reports): void
    {
        $reports->full($this->repository->loadMissing('sync'), $this->period, $this->includeBots);
    }

    public function failed(): void
    {
        app(ReportCache::class)->fullFailed($this->repository->loadMissing('sync'), $this->period, $this->includeBots);
    }
}
