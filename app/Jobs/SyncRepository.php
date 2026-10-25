<?php

namespace App\Jobs;

use App\Enums\RepositorySyncStatus;
use App\Enums\SystemEventLevel;
use App\Enums\SystemEventType;
use App\GitHub\RateLimitedException;
use App\GitHub\RepositorySynchronizer;
use App\GitHub\SyncInProgressException;
use App\Models\Repository;
use App\Models\SystemEvent;
use App\Support\Demo;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncRepository implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const int MaxRuntimeSeconds = 1800;

    /**
     * How long a sync may keep waiting out rate limits before giving up.
     */
    public const int MaxRateLimitedHours = 24;

    public int $timeout = self::MaxRuntimeSeconds;

    /**
     * Releases while waiting out a rate limit don't count as failures, but any exception fails the job.
     */
    public int $maxExceptions = 1;

    public int $uniqueFor = self::MaxRuntimeSeconds;

    public function __construct(public Repository $repository) {}

    /**
     * Queue a sync and mark the cache as refreshing so the UI can reflect it right away. Demo visitors
     * can't, so only the scheduler keeps the demo fresh.
     *
     * @param  string  $reason  Why the sync was asked for, for the log.
     */
    public static function trigger(Repository $repository, string $reason = 'requested'): void
    {
        $state = $repository->syncState();

        if (Demo::visiting() || $state->isInProgress()) {
            return;
        }

        $state->update(['status' => RepositorySyncStatus::Queued, 'rate_limited_until' => null]);

        static::dispatch($repository);

        SystemEvent::record(SystemEventType::SyncQueued, "Queued a sync of {$repository->fullName()} ({$reason})", $repository, ['reason' => $reason]);
    }

    public function uniqueId(): string
    {
        return (string) $this->repository->id;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(self::MaxRateLimitedHours);
    }

    public function handle(RepositorySynchronizer $synchronizer): void
    {
        try {
            $synchronizer->sync($this->repository);
        } catch (SyncInProgressException) {
            // Another worker or the CLI is already refreshing this repository.
            SystemEvent::record(SystemEventType::SyncSkipped, "Skipped a queued sync of {$this->repository->fullName()}, another one is already running", $this->repository);
        } catch (RateLimitedException $exception) {
            // Progress is checkpointed as the sync goes, so the next attempt picks up where this one stopped.
            $this->repository->syncState()->update([
                'status' => RepositorySyncStatus::Queued,
                'rate_limited_until' => $exception->retryAt,
            ]);

            $this->release(max(1, (int) ceil(now()->diffInSeconds($exception->retryAt))) + 5);

            SystemEvent::record(
                SystemEventType::SyncRateLimited,
                "Paused the sync of {$this->repository->fullName()} until the GitHub {$exception->resource} rate limit resets",
                $this->repository,
                ['resource' => $exception->resource, 'retry_at' => $exception->retryAt->toIso8601String(), 'phase' => $this->repository->sync()->value('phase')],
                SystemEventLevel::Warning,
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $state = $this->repository->syncState();

        SystemEvent::record(SystemEventType::SyncFailed, "Sync of {$this->repository->fullName()} failed: ".($exception?->getMessage() ?? 'unknown error'), $this->repository, [
            'phase' => $state->phase,
            'exception' => $exception === null ? null : $exception::class,
            'attempts' => $this->attempts(),
        ], SystemEventLevel::Error);

        $state->update([
            'status' => RepositorySyncStatus::Failed,
            'rate_limited_until' => null,
            'error' => $exception?->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
