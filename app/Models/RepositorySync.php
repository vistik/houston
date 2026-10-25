<?php

namespace App\Models;

use App\Enums\DeploymentSource;
use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\Jobs\SyncRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bookkeeping about the GitHub cache of a repository.
 *
 * @property CarbonImmutable|null $rate_limited_until
 * @property RepositorySyncStatus|null $status Null only on a sync that was never saved.
 * @property RepositorySyncPhase|null $phase
 * @property DeploymentSource|null $deploy_source
 * @property CarbonImmutable|null $phase_started_at
 * @property bool $backfill
 * @property bool|null $is_private Null until a sync has fetched it.
 * @property CarbonImmutable|null $history_from
 * @property CarbonImmutable|null $repository_created_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $pull_requests_synced_at
 * @property CarbonImmutable|null $pull_requests_resume_started_at
 * @property CarbonImmutable|null $pull_requests_from
 * @property CarbonImmutable|null $pull_requests_reached_at
 * @property int $pull_requests_fetched
 * @property CarbonImmutable|null $workflow_runs_synced_at
 * @property CarbonImmutable|null $workflow_runs_from
 * @property CarbonImmutable|null $workflow_runs_reached_at
 * @property int $workflow_runs_fetched
 * @property CarbonImmutable|null $deployments_synced_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $last_duration_seconds
 */
#[Unguarded]
class RepositorySync extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RepositorySyncStatus::class,
            'phase' => RepositorySyncPhase::class,
            'deploy_source' => DeploymentSource::class,
            'rate_limited_until' => 'immutable_datetime',
            'phase_started_at' => 'immutable_datetime',
            'backfill' => 'boolean',
            'is_private' => 'boolean',
            'history_from' => 'immutable_datetime',
            'repository_created_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'pull_requests_synced_at' => 'immutable_datetime',
            'pull_requests_resume_started_at' => 'immutable_datetime',
            'pull_requests_from' => 'immutable_datetime',
            'pull_requests_reached_at' => 'immutable_datetime',
            'workflow_runs_synced_at' => 'immutable_datetime',
            'workflow_runs_from' => 'immutable_datetime',
            'workflow_runs_reached_at' => 'immutable_datetime',
            'deployments_synced_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * A sync that has been queued or running for longer than the job may live is considered abandoned,
     * e.g. because no queue worker picked it up or the worker died mid-sync. One waiting out a rate limit
     * is in progress until shortly after the limit resets.
     */
    public function isInProgress(): bool
    {
        if (! in_array($this->status, [RepositorySyncStatus::Queued, RepositorySyncStatus::Running], true)) {
            return false;
        }

        if ($this->isRateLimited()) {
            return true;
        }

        return (bool) $this->updated_at?->gt(now()->subSeconds(SyncRepository::MaxRuntimeSeconds));
    }

    /**
     * Determine whether the sync is paused until a GitHub rate limit resets.
     */
    public function isRateLimited(): bool
    {
        return $this->status === RepositorySyncStatus::Queued && (bool) $this->rate_limited_until?->gt(now()->subSeconds(SyncRepository::MaxRuntimeSeconds));
    }

    /**
     * How far back the cache reaches. Caches from before this was recorded were synced with the default lookback.
     */
    public function coveredFrom(): ?CarbonImmutable
    {
        if ($this->history_from !== null || $this->pull_requests_synced_at === null) {
            return $this->history_from;
        }

        return CarbonImmutable::now()->subDays(Repository::defaultLookbackDays())->startOfDay();
    }

    public function enterPhase(RepositorySyncPhase $phase): void
    {
        $this->update(['phase' => $phase, 'phase_started_at' => now()]);
    }
}
