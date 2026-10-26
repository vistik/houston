<?php

namespace App\Models;

use App\Enums\WorkflowRunConclusion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached job of one attempt of a re-run GitHub Actions workflow run.
 *
 * @property int $run_attempt
 * @property WorkflowRunConclusion|null $conclusion Null until the job completes.
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $duration_seconds
 * @property string|null $failed_step The step the job failed in, or null when it didn't fail or GitHub didn't say.
 */
#[Unguarded]
#[WithoutTimestamps]
class WorkflowJob extends Model
{
    /**
     * SQL condition for jobs that broke their run.
     */
    public const string FailedCondition = "(workflow_jobs.conclusion in ('".WorkflowRunConclusion::Failure->value."', '".WorkflowRunConclusion::TimedOut->value."'))";

    /**
     * SQL condition for jobs that passed.
     */
    public const string PassedCondition = "(workflow_jobs.conclusion = '".WorkflowRunConclusion::Success->value."')";

    /**
     * Whether a step is one of GitHub's own around every job, or an action that checks out the code, sets
     * up a toolchain, caches or moves artifacts. When those fail, it's usually the runner or the network
     * rather than the code. Named steps can't be told apart, so this only catches steps left unnamed.
     */
    public static function isSetupStep(string $step): bool
    {
        return in_array($step, ['Set up job', 'Initialize containers', 'Stop containers', 'Complete job'], true)
            || preg_match('~^(Post )?Run (actions/[\w.-]+|[\w.-]+/([\w.-]*setup[\w.-]*))@~i', $step) === 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conclusion' => WorkflowRunConclusion::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkflowRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}
