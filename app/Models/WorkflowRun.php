<?php

namespace App\Models;

use App\Enums\WorkflowRunConclusion;
use App\Enums\WorkflowRunStatus;
use App\Models\Concerns\MemoizesImmutableDates;
use Carbon\CarbonImmutable;
use Database\Factories\WorkflowRunFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached GitHub Actions workflow run.
 *
 * @property WorkflowRunStatus $status
 * @property WorkflowRunConclusion|null $conclusion Null until the run completes.
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $run_started_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $duration_seconds
 * @property int|null $queue_seconds
 */
#[Unguarded]
#[WithoutTimestamps]
class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use HasFactory;

    use MemoizesImmutableDates;

    /**
     * GitHub-hosted jobs are capped at six hours. Runs that "took" longer were never
     * executed, e.g. they sat waiting for approval until GitHub expired them.
     */
    public const int MaxRealisticSeconds = 6 * 3600 + 900;

    /**
     * The conclusions that broke the build, as an SQL list.
     */
    private const string FailedConclusions = "'".WorkflowRunConclusion::Failure->value."', '".WorkflowRunConclusion::TimedOut->value."', '".WorkflowRunConclusion::StartupFailure->value."'";

    /**
     * SQL condition for runs that actually executed and produced a pass/fail verdict.
     */
    public const string DecisiveCondition = "(conclusion in ('".WorkflowRunConclusion::Success->value."', ".self::FailedConclusions.') and (duration_seconds is null or duration_seconds <= '.self::MaxRealisticSeconds.'))';

    /**
     * SQL condition for decisive runs that broke the build.
     */
    public const string FailedCondition = '('.self::DecisiveCondition.' and conclusion in ('.self::FailedConclusions.'))';

    /**
     * SQL expression for the wall-clock seconds a run took, or null for runs that never really executed.
     */
    public const string DurationExpression = '(case when duration_seconds <= '.self::MaxRealisticSeconds.' then duration_seconds end)';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'conclusion' => WorkflowRunConclusion::class,
            'created_at' => 'immutable_datetime',
            'run_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function decisive(Builder $query): void
    {
        $query->whereRaw(self::DecisiveCondition);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->whereRaw(self::FailedCondition);
    }

    /**
     * Wall-clock seconds between two moments, computed once when the run is cached.
     */
    public static function durationBetween(?CarbonImmutable $startedAt, ?CarbonImmutable $completedAt): ?int
    {
        if ($startedAt === null || $completedAt === null) {
            return null;
        }

        return max(0, (int) $startedAt->diffInSeconds($completedAt));
    }
}
