<?php

namespace App\Jobs;

use App\GitHub\DependencySynchronizer;
use App\GitHub\RateLimitedException;
use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks how far behind a repository's dependencies are, after a sync and on its own, so a registry
 * that is slow or down never holds up or fails the sync.
 */
class CheckDependencies implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public Repository $repository) {}

    public function uniqueId(): string
    {
        return (string) $this->repository->id;
    }

    public function handle(DependencySynchronizer $dependencies): void
    {
        try {
            $dependencies->sync($this->repository);
        } catch (RateLimitedException) {
            // The next sync checks again; dependencies don't change so fast that waiting for the reset is worth it.
        }
    }
}
