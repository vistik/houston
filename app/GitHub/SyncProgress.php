<?php

namespace App\GitHub;

use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\Models\Repository;
use App\Models\RepositorySync;
use Carbon\CarbonImmutable;

/**
 * Describes how far along a repository sync is and estimates how long it has left.
 */
class SyncProgress
{
    /**
     * A running sync without any progress for this long is likely waiting out a rate limit or has died.
     */
    public const int StalledAfterSeconds = 120;

    /**
     * Extrapolating from elapsed time is too noisy before this much of the sync is done.
     */
    private const float MinimumFractionToExtrapolate = 0.05;

    private RepositorySync $sync;

    public function __construct(private Repository $repository)
    {
        $this->sync = $repository->sync ?? new RepositorySync;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $sync = $this->sync;
        $running = $sync->status === RepositorySyncStatus::Running && $sync->isInProgress();
        $fraction = $running ? $this->fraction() : null;

        return [
            'status' => $sync->status,
            'inProgress' => $sync->isInProgress(),
            'stalled' => $running && $sync->updated_at?->lt(now()->subSeconds(self::StalledAfterSeconds)),
            'backfill' => $running ? $sync->backfill : $this->repository->needsBackfill(),
            'phase' => $sync->phase,
            'phases' => $this->phases(),
            'progress' => $fraction,
            'estimatedFinishAt' => $this->estimatedFinishAt($running, $fraction)?->toIso8601String(),
            'pullRequestsFetched' => $sync->pull_requests_fetched,
            'workflowRunsFetched' => $sync->workflow_runs_fetched,
            'historyFrom' => $sync->coveredFrom()?->toIso8601String(),
            'rateLimitedUntil' => $sync->isRateLimited() ? $sync->rate_limited_until?->toIso8601String() : null,
            'queuedAt' => $sync->status === RepositorySyncStatus::Queued ? $sync->updated_at?->toIso8601String() : null,
            'startedAt' => $sync->started_at?->toIso8601String(),
            'lastActivityAt' => $sync->updated_at?->toIso8601String(),
            'finishedAt' => $sync->finished_at?->toIso8601String(),
            'lastDurationSeconds' => $sync->last_duration_seconds,
            'error' => $sync->error,
        ];
    }

    /**
     * @return list<array{key: RepositorySyncPhase, label: string, state: SyncPhaseState, progress: float|null, startedAt: string|null}>
     */
    private function phases(): array
    {
        $sync = $this->sync;
        $phases = RepositorySyncPhase::cases();
        $current = $sync->phase === null ? false : array_search($sync->phase, $phases, true);
        $tracking = $current !== false && $this->hasStarted();

        return array_map(function (RepositorySyncPhase $phase, int $index) use ($sync, $current, $tracking): array {
            $state = match (true) {
                $this->skipped($phase) => SyncPhaseState::Skipped,
                ! $tracking => $sync->status === RepositorySyncStatus::Succeeded ? SyncPhaseState::Done : SyncPhaseState::Pending,
                $this->runsConcurrently($phase, $current) => $this->concurrentState($phase),
                $index < $current => SyncPhaseState::Done,
                $index === $current => $sync->status === RepositorySyncStatus::Failed ? SyncPhaseState::Failed : SyncPhaseState::Active,
                default => SyncPhaseState::Pending,
            };

            return [
                'key' => $phase,
                'label' => $phase->label(),
                'state' => $state,
                'progress' => $state === SyncPhaseState::Active || $state === SyncPhaseState::Failed ? $this->phaseProgress($phase) : null,
                'startedAt' => $index === $current ? $sync->phase_started_at?->toIso8601String() : null,
            ];
        }, $phases, array_keys($phases));
    }

    /**
     * Rough share of a sync's time spent in a phase, used to turn phase progress into overall progress.
     */
    private static function weight(RepositorySyncPhase $phase): float
    {
        return match ($phase) {
            RepositorySyncPhase::Details => 0.02,
            RepositorySyncPhase::PullRequests => 0.6,
            RepositorySyncPhase::OpenPullRequests => 0.08,
            RepositorySyncPhase::WorkflowRuns => 0.25,
            RepositorySyncPhase::Deployments => 0.05,
        };
    }

    /**
     * For phases fetched alongside the pull requests, the column each moves to the sync's start once done.
     */
    private static function syncedAtColumn(RepositorySyncPhase $phase): ?string
    {
        return match ($phase) {
            RepositorySyncPhase::WorkflowRuns => 'workflow_runs_synced_at',
            RepositorySyncPhase::Deployments => 'deployments_synced_at',
            default => null,
        };
    }

    /**
     * Workflow runs and deployments are fetched alongside the pull request phases, which are what the phase
     * column follows.
     */
    private function runsConcurrently(RepositorySyncPhase $phase, int|false $current): bool
    {
        return self::syncedAtColumn($phase) !== null
            && $current !== false
            && $current >= array_search(RepositorySyncPhase::PullRequests, RepositorySyncPhase::cases(), true);
    }

    /**
     * A concurrent phase is done once it reaches the start of the current attempt.
     */
    private function concurrentState(RepositorySyncPhase $phase): SyncPhaseState
    {
        $sync = $this->sync;
        $syncedAt = $sync->{self::syncedAtColumn($phase)};

        return match (true) {
            $sync->started_at !== null && $syncedAt?->gte($sync->started_at) => SyncPhaseState::Done,
            $sync->status === RepositorySyncStatus::Failed => SyncPhaseState::Failed,
            default => SyncPhaseState::Active,
        };
    }

    /**
     * Only the first sync fetches open pull requests older than the lookback window. Afterwards any change
     * to them bumps their updatedAt, so later syncs, including ones that extend the lookback, skip it.
     */
    private function skipped(RepositorySyncPhase $phase): bool
    {
        if ($phase !== RepositorySyncPhase::OpenPullRequests) {
            return false;
        }

        return $this->hasStarted()
            ? ! $this->sync->backfill || $this->sync->history_from !== null
            : $this->sync->pull_requests_synced_at !== null;
    }

    /**
     * Determine whether the phase columns describe the current attempt: it is running, failed part way, or
     * paused by a rate limit until it can carry on.
     */
    private function hasStarted(): bool
    {
        return in_array($this->sync->status, [RepositorySyncStatus::Running, RepositorySyncStatus::Failed], true)
            || $this->sync->isRateLimited();
    }

    /**
     * How far through its time window a phase has got, for phases that walk through time.
     */
    private function phaseProgress(RepositorySyncPhase $phase): ?float
    {
        $sync = $this->sync;

        return match ($phase) {
            // Pull requests are fetched newest first, walking back to the watermark.
            RepositorySyncPhase::PullRequests => $this->share(
                $sync->pull_requests_reached_at ?? $sync->pull_requests_resume_started_at,
                $sync->pull_requests_resume_started_at,
                $sync->pull_requests_from,
            ),
            // Workflow runs are fetched oldest first, walking forward to the start of the sync.
            RepositorySyncPhase::WorkflowRuns => $this->share($sync->workflow_runs_reached_at, $sync->workflow_runs_from, $sync->started_at),
            default => null,
        };
    }

    /**
     * The share of the way from start to end that the given point has covered.
     */
    private function share(?CarbonImmutable $reached, ?CarbonImmutable $start, ?CarbonImmutable $end): ?float
    {
        if ($reached === null || $start === null || $end === null) {
            return null;
        }

        $total = abs($start->diffInSeconds($end));

        if ($total < 1) {
            return null;
        }

        return max(0.0, min(1.0, abs($start->diffInSeconds($reached)) / $total));
    }

    private function fraction(): float
    {
        $done = 0.0;
        $total = 0.0;

        foreach ($this->phases() as $phase) {
            if ($phase['state'] === SyncPhaseState::Skipped) {
                continue;
            }

            $weight = self::weight($phase['key']);
            $total += $weight;

            $done += match ($phase['state']) {
                SyncPhaseState::Done => $weight,
                SyncPhaseState::Active => $weight * ($phase['progress'] ?? 0),
                default => 0,
            };
        }

        return $total > 0 ? $done / $total : 0.0;
    }

    private function estimatedFinishAt(bool $running, ?float $fraction): ?CarbonImmutable
    {
        $sync = $this->sync;
        $previous = $sync->last_duration_seconds;

        if (! $running) {
            // Without history, a queued backfill could take anything from seconds to an hour.
            if ($sync->status !== RepositorySyncStatus::Queued || ! $sync->isInProgress() || $previous === null || $this->repository->needsBackfill()) {
                return null;
            }

            $startsAt = $sync->isRateLimited() ? $sync->rate_limited_until?->max(CarbonImmutable::now()) : null;

            return ($startsAt ?? CarbonImmutable::now())->addSeconds($previous);
        }

        $startedAt = $sync->started_at ?? CarbonImmutable::now();
        $elapsed = $startedAt->diffInSeconds(CarbonImmutable::now());

        // Incremental syncs tend to take as long as the last one did, which beats extrapolating.
        if (! $sync->backfill && $previous !== null && $elapsed < $previous) {
            return $startedAt->addSeconds($previous);
        }

        if ($fraction === null || $fraction < self::MinimumFractionToExtrapolate) {
            return null;
        }

        return $startedAt->addSeconds((int) round($elapsed / $fraction));
    }
}
