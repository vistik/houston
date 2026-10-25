<?php

namespace App\Models;

use App\Enums\RepositoryCardView;
use App\Enums\RepositoryView;
use App\Support\Demo;
use Carbon\CarbonImmutable;
use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int|null $lookback_days
 * @property list<int>|null $excluded_workflow_ids
 * @property string|null $view
 * @property string|null $card_view
 */
#[Fillable(['owner', 'name', 'lookback_days', 'excluded_workflow_ids', 'view', 'card_view'])]
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lookback_days' => 'integer',
            'excluded_workflow_ids' => 'array',
        ];
    }

    /**
     * @return HasMany<PullRequest, $this>
     */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * Workflow runs that count towards the CI metrics, leaving out the workflows excluded from them.
     *
     * @return HasMany<WorkflowRun, $this>
     */
    public function measuredWorkflowRuns(): HasMany
    {
        return $this->workflowRuns()->whereNotIn('workflow_id', $this->excludedWorkflowIds());
    }

    /**
     * @return list<int>
     */
    public function excludedWorkflowIds(): array
    {
        return $this->excluded_workflow_ids ?? [];
    }

    /**
     * The view this repository's page opens in, falling back to the full one when none was picked or the
     * picked one is no longer known.
     */
    public function pageView(): RepositoryView
    {
        return RepositoryView::tryFrom((string) Demo::preference("repositories.{$this->id}.view", $this->view)) ?? RepositoryView::Full;
    }

    /**
     * What this repository's card shows: the picked card, or the one that goes with its page's view.
     */
    public function cardView(): RepositoryCardView
    {
        return RepositoryCardView::tryFrom((string) Demo::preference("repositories.{$this->id}.card_view", $this->card_view)) ?? RepositoryCardView::for($this->pageView());
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * @return HasMany<Dependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(Dependency::class);
    }

    /**
     * @return HasMany<DependencySnapshot, $this>
     */
    public function dependencySnapshots(): HasMany
    {
        return $this->hasMany(DependencySnapshot::class);
    }

    /**
     * @return HasOne<RepositorySync, $this>
     */
    public function sync(): HasOne
    {
        return $this->hasOne(RepositorySync::class);
    }

    public function fullName(): string
    {
        return "{$this->owner}/{$this->name}";
    }

    /**
     * Fetch the sync bookkeeping row, creating it when the cache has been wiped.
     */
    public function syncState(): RepositorySync
    {
        return $this->sync()->firstOrCreate();
    }

    /**
     * Stored as the lookback to cache the whole history of the repository.
     */
    public const int AllTime = 0;

    /**
     * Before the first sync tells when the repository was created, an all-time lookback reaches back to
     * when GitHub launched.
     */
    private const string GitHubLaunchedOn = '2008-02-08';

    /**
     * How many days of history to cache, falling back to the configured default. An all-time lookback
     * covers the days since the repository was created.
     */
    public function lookbackDays(): int
    {
        if ($this->lookback_days !== self::AllTime) {
            return $this->lookback_days ?? self::defaultLookbackDays();
        }

        $createdAt = $this->sync?->repository_created_at;
        $createdAt ??= CarbonImmutable::parse(self::GitHubLaunchedOn);

        return max(1, (int) round($createdAt->startOfDay()->diffInDays(CarbonImmutable::today())));
    }

    public function hasAllTimeLookback(): bool
    {
        return $this->lookback_days === self::AllTime;
    }

    public static function defaultLookbackDays(): int
    {
        return config()->integer('houston.lookback_days');
    }

    /**
     * The longest history any watched repository caches, which bounds reports across repositories.
     */
    public static function longestLookbackDays(): int
    {
        return self::query()->with('sync')->get()
            ->map(fn (self $repository): int => $repository->lookbackDays())
            ->push(self::defaultLookbackDays())
            ->max();
    }

    public function lookbackStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($this->lookbackDays())->startOfDay();
    }

    /**
     * Determine whether the cache has yet to reach back as far as the lookback asks for.
     */
    public function needsBackfill(): bool
    {
        $coveredFrom = $this->sync?->coveredFrom();

        return $coveredFrom === null || $coveredFrom->gt($this->lookbackStart());
    }

    /**
     * Determine whether the cached GitHub data is older than the configured freshness window.
     */
    public function isStale(): bool
    {
        $finishedAt = $this->sync?->finished_at;

        return $finishedAt === null
            || $finishedAt->lt(now()->subMinutes(config()->integer('houston.stale_after_minutes')));
    }
}
