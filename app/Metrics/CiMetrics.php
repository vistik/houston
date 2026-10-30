<?php

namespace App\Metrics;

use App\Enums\WorkflowRunStatus;
use App\Metrics\Ci\CiJob;
use App\Metrics\Ci\CiWindow;
use App\Models\Repository;
use App\Models\WorkflowJob;
use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use LogicException;
use stdClass;

/**
 * CI signals from GitHub Actions workflow runs, computed in the database.
 *
 * Busy repositories have tens of thousands of runs, so everything that can be counted, summed or
 * grouped happens in SQL. Only medians (which SQLite can't compute) and working-time durations are
 * finished in PHP, from small result sets.
 *
 * @phpstan-type Streak array{workflow_id: int, latest_name: string, last_run_at: CarbonImmutable, broken_at: CarbonImmutable|null, recovered_at: CarbonImmutable|null}
 * @phpstan-type FlakyJob array{workflowId: int, workflow: string, name: string, runs: int, failures: int, seconds: int, waitSeconds: int, lastFailure: mixed}
 * @phpstan-type FlakyJobSummary array{jobs: Collection<int, FlakyJob>, flaky: QueryBuilder, runs: int, seconds: int, waitSeconds: int, pendingRuns: int, fetchedRuns: int}
 */
class CiMetrics
{
    /**
     * Failure streaks on the default branch, computed once per report.
     *
     * @var Collection<int, Streak>|null
     */
    private ?Collection $streaks = null;

    /**
     * Flaky jobs per window, by its start and end, as the metrics and the flakiest jobs both read them.
     *
     * @var array<string, FlakyJobSummary>
     */
    private array $flakyJobSummaries = [];

    /**
     * @param  CarbonImmutable  $since  How far back runs are considered, e.g. the start of the previous period.
     * @param  CiFailureRateScope  $failureRateScope  Which runs the CI failure rate and its trend count.
     * @param  CiJob|null  $job  One job to narrow the flaky-job metrics to.
     */
    public function __construct(
        private Repository $repository,
        private ?string $defaultBranch,
        private CarbonImmutable $since,
        private WorkingTime $time,
        private CarbonImmutable $now,
        private CiFailureRateScope $failureRateScope = CiFailureRateScope::DefaultBranch,
        private ?CiJob $job = null,
    ) {}

    /**
     * The runs within a window, for metrics to calculate from. Queries run when a metric first needs them.
     */
    public function window(CarbonImmutable $from, CarbonImmutable $to): CiWindow
    {
        return new CiWindow(
            failureRateScope: $this->failureRateScope,
            makeCounts: fn (): object => $this->counts($this->runs($from, $to))->first(),
            makeDurations: fn (): Collection => $this->runs($from, $to)->decisive()->toBase()->get(['duration_seconds', 'queue_seconds']),
            makeCommitDurations: fn (): Collection => $this->runs($from, $to)->decisive()->toBase()
                ->selectRaw('max(duration_seconds) as duration_seconds')
                ->groupBy('head_sha')
                ->pluck('duration_seconds'),
            makeRecoveryTimes: fn (): Collection => $this->recoveryTimes($from, $to),
            makeFlakyRate: fn (): ?float => $this->flakyRate($from, $to),
            makeRedShare: fn (): ?float => $this->redShare($from, $to),
            makeFlakyJobs: function () use ($from, $to): array {
                $summary = $this->flakyJobSummary($from, $to);

                return ['runs' => $summary['runs'], 'seconds' => $summary['seconds'], 'waitSeconds' => $summary['waitSeconds'], 'pendingRuns' => $summary['pendingRuns'], 'fetchedRuns' => $summary['fetchedRuns']];
            },
        );
    }

    /**
     * Decisive runs per trend bucket, and the decisive and failed runs the CI failure rate counts.
     * Runs are counted per hour in the database and the hours sorted into buckets here, since a
     * bucket's length in seconds changes across a daylight saving change.
     *
     * @return array<int, array{runs: int, rated: int, failed: int}>
     */
    public function trend(Period $period): array
    {
        [$rated, $bindings] = $this->failureRateScope === CiFailureRateScope::DefaultBranch ? $this->defaultBranchCondition() : ['(1 = 1)', []];
        $start = $period->chartFrom->getTimestamp();
        $buckets = [];

        $hours = $this->runs($period->chartFrom, $period->chartedUntil)
            ->decisive()
            ->toBase()
            ->selectRaw(
                $this->hourExpression()." as hour, count(*) as runs, sum(case when {$rated} then 1 else 0 end) as rated, sum(case when ".WorkflowRun::FailedCondition." and {$rated} then 1 else 0 end) as failed",
                [$start, ...$bindings, ...$bindings],
            )
            ->groupBy('hour')
            ->get();

        foreach ($hours as $row) {
            $index = $period->bucketIndex(CarbonImmutable::createFromTimestamp($start + (int) $row->hour * 3600));
            $bucket = $buckets[$index] ?? ['runs' => 0, 'rated' => 0, 'failed' => 0];

            $buckets[$index] = [
                'runs' => $bucket['runs'] + (int) $row->runs,
                'rated' => $bucket['rated'] + (int) $row->rated,
                'failed' => $bucket['failed'] + (int) $row->failed,
            ];
        }

        return $buckets;
    }

    /**
     * Health of each workflow over the period, busiest first. Excluded workflows are listed too, and
     * flagged, so they can be included again.
     *
     * @return array<int, array<string, mixed>>
     */
    public function workflows(Period $period): array
    {
        $window = fn (): Builder => $this->runs($period->from, $period->to, withExcluded: true);
        $excluded = $this->repository->excludedWorkflowIds();

        $counts = $this->counts($window())
            ->addSelect('workflow_id')
            ->selectRaw('min(id) as first_id')
            ->groupBy('workflow_id')
            ->get();

        $flaky = $this->flakyCommits($window(), 'workflow_id')
            ->addSelect('workflow_id')
            ->groupBy('workflow_id')
            ->get()
            ->keyBy('workflow_id');

        $durations = $window()->decisive()->toBase()->get(['workflow_id', 'duration_seconds', 'queue_seconds'])->groupBy('workflow_id');

        $latest = $this->repository->getConnection()->query()
            ->fromSub($window()->toBase()->select(['workflow_id', 'name', 'conclusion', 'status', 'url'])
                ->selectRaw('row_number() over (partition by workflow_id order by created_at desc, id desc) as position'), 'runs')
            ->where('position', 1)
            ->get()
            ->keyBy('workflow_id');

        return $counts
            ->sortBy([['runs', 'desc'], ['first_id', 'asc']])
            ->map(function (object $row) use ($flaky, $durations, $latest, $excluded): array {
                $runs = $durations->get($row->workflow_id, collect());
                $last = $latest[$row->workflow_id];
                $commits = $flaky->get($row->workflow_id);

                return [
                    'workflowId' => (int) $row->workflow_id,
                    'name' => $last->name,
                    'excluded' => in_array((int) $row->workflow_id, $excluded, true),
                    'runs' => (int) $row->runs,
                    'failureRate' => Stats::ratio((int) $row->failed, (int) $row->decisive),
                    'defaultBranchFailureRate' => Stats::ratio((int) $row->default_branch_failed, (int) $row->default_branch),
                    'rerunRate' => Stats::ratio((int) $row->reruns, (int) $row->decisive),
                    'flakyRate' => $commits === null ? null : Stats::ratio((int) $commits->flaky, (int) $commits->commits),
                    'queueTime' => Stats::median($runs->pluck('queue_seconds')),
                    'duration' => Stats::median($runs->pluck('duration_seconds')),
                    'durationP90' => Stats::percentile($runs->pluck('duration_seconds'), 90),
                    'minutes' => (int) round((int) $row->seconds / 60),
                    'lastConclusion' => $last->conclusion ?? $last->status,
                    'lastUrl' => $last->url,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The jobs that most often failed and then passed when their run was re-run in the period, with what
     * they cost: the CI time of their failed attempts, and the working time from a failed attempt
     * finishing to the re-run passing, while whoever pushed waited. Each job lists the steps its failed
     * attempts failed in, most often first, flagging GitHub's own setup steps, which point at the runner
     * or the network rather than the tests, and how many runs it was flaky in the period before.
     *
     * Alongside the flakiest jobs: how many jobs were flaky at all and what all of them cost, and how many
     * re-runs in the period are still waiting for their jobs, so an empty list can be told from one that
     * isn't known yet.
     *
     * @return array{jobs: list<array{workflowId: int, workflow: string, name: string, runs: int, previousRuns: int, failures: int, seconds: int, waitSeconds: int, steps: list<array{name: string, failures: int, setup: bool}>, url: string|null}>, count: int, seconds: int, waitSeconds: int, pendingRuns: int}
     */
    public function flakyJobs(Period $period, int $limit = 5): array
    {
        $summary = $this->flakyJobSummary($period->from, $period->to);
        $previous = $this->flakyJobSummary($period->previousFrom, $period->from)['jobs']->keyBy(self::flakyJobKey(...));
        $top = $summary['jobs']->take($limit);

        $steps = $this->repository->getConnection()->query()
            ->fromSub($summary['flaky'], 'flaky')
            ->join('workflow_jobs', fn (JoinClause $join) => $join->on('workflow_jobs.workflow_run_id', '=', 'flaky.run_id')->on('workflow_jobs.name', '=', 'flaky.name'))
            ->whereRaw(WorkflowJob::FailedCondition)
            ->whereNotNull('workflow_jobs.failed_step')
            ->select(['flaky.workflow_id', 'flaky.name', 'workflow_jobs.failed_step'])
            ->selectRaw('count(*) as failures')
            ->groupBy('flaky.workflow_id', 'flaky.name', 'workflow_jobs.failed_step')
            ->get()
            ->groupBy(fn (stdClass $step): string => "{$step->workflow_id}:{$step->name}");

        $urls = WorkflowJob::query()
            ->whereIn('github_id', $top->pluck('lastFailure'))
            ->pluck('url', 'github_id');

        return [
            'jobs' => array_values($top->map(fn (array $job): array => [
                'workflowId' => $job['workflowId'],
                'workflow' => $job['workflow'],
                'name' => $job['name'],
                'runs' => $job['runs'],
                'previousRuns' => $previous->get(self::flakyJobKey($job))['runs'] ?? 0,
                'failures' => $job['failures'],
                'seconds' => $job['seconds'],
                'waitSeconds' => $job['waitSeconds'],
                'steps' => array_values($steps->get(self::flakyJobKey($job), collect())
                    ->sortBy([['failures', 'desc'], ['failed_step', 'asc']])
                    ->map(fn (stdClass $step): array => [
                        'name' => (string) $step->failed_step,
                        'failures' => (int) $step->failures,
                        'setup' => WorkflowJob::isSetupStep((string) $step->failed_step),
                    ])
                    ->all()),
                'url' => isset($urls[$job['lastFailure']]) ? (string) $urls[$job['lastFailure']] : null,
            ])->all()),
            'count' => $summary['jobs']->count(),
            'seconds' => $summary['seconds'],
            'waitSeconds' => $summary['waitSeconds'],
            'pendingRuns' => $summary['pendingRuns'],
        ];
    }

    /**
     * Every job that failed and then passed on a re-run within a window, flakiest first, or only the job
     * the metrics are narrowed to. Jobs are told apart by workflow and name, so each leg of a matrix
     * counts on its own. Runs where several jobs were flaky are waited on once in the total. Excluded
     * workflows are left out.
     *
     * @return FlakyJobSummary
     */
    private function flakyJobSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $key = $from->getTimestamp().':'.$to->getTimestamp();

        if (isset($this->flakyJobSummaries[$key])) {
            return $this->flakyJobSummaries[$key];
        }

        $failed = WorkflowJob::FailedCondition;
        $passed = WorkflowJob::PassedCondition;

        // One row per run and job that failed on one attempt and passed on a later one.
        $flaky = $this->runs($from, $to)
            ->join('workflow_jobs', 'workflow_jobs.workflow_run_id', '=', 'workflow_runs.id')
            ->when($this->job, fn (Builder $query, CiJob $job) => $query->where('workflow_runs.workflow_id', $job->workflowId)->where('workflow_jobs.name', $job->name))
            ->toBase()
            ->select(['workflow_runs.workflow_id', 'workflow_jobs.name'])
            ->selectRaw(implode(', ', [
                'workflow_runs.id as run_id',
                'max(workflow_runs.name) as workflow',
                "sum(case when {$failed} then 1 else 0 end) as failures",
                "coalesce(sum(case when {$failed} then workflow_jobs.duration_seconds end), 0) as seconds",
                "max(case when {$failed} then workflow_jobs.github_id end) as last_failure",
                "min(case when {$failed} then workflow_jobs.completed_at end) as failed_at",
                "max(case when {$passed} then workflow_jobs.completed_at end) as passed_at",
            ]))
            ->groupBy('workflow_runs.id', 'workflow_runs.workflow_id', 'workflow_jobs.name')
            ->havingRaw("max(case when {$passed} then workflow_jobs.run_attempt end) > min(case when {$failed} then workflow_jobs.run_attempt end)");

        $runs = $this->repository->getConnection()->query()->fromSub($flaky, 'flaky')->get();
        $waited = function (Collection $runs): int {
            $failedAt = $runs->pluck('failed_at')->filter()->min();
            $passedAt = $runs->pluck('passed_at')->filter()->max();

            return $failedAt === null || $passedAt === null ? 0 : $this->time->between(CarbonImmutable::parse($failedAt), CarbonImmutable::parse($passedAt));
        };

        $jobs = $runs->groupBy(fn (stdClass $run): string => "{$run->workflow_id}:{$run->name}")
            ->map(fn (Collection $runs): array => [
                'workflowId' => (int) $runs->first()->workflow_id,
                'workflow' => (string) $runs->max('workflow'),
                'name' => (string) $runs->first()->name,
                'runs' => $runs->count(),
                'failures' => (int) $runs->sum('failures'),
                'seconds' => (int) $runs->sum('seconds'),
                'waitSeconds' => (int) $runs->sum(fn (stdClass $run): int => $waited(collect([$run]))),
                'lastFailure' => $runs->max('last_failure'),
            ])
            ->sortBy([['runs', 'desc'], ['seconds', 'desc'], ['name', 'asc']])
            ->values();

        // Re-runs are only known to be fetched once their latest attempt's jobs are.
        $reruns = $this->runs($from, $to)
            ->where('status', WorkflowRunStatus::Completed)
            ->where('run_attempt', '>', 1)
            // A job's name is only known once its run's jobs are, so a job only narrows to its workflow here.
            ->when($this->job, fn (Builder $query, CiJob $job) => $query->where('workflow_id', $job->workflowId))
            ->toBase()
            ->selectRaw('coalesce(sum(case when jobs_attempt >= run_attempt then 1 else 0 end), 0) as fetched, coalesce(sum(case when jobs_attempt is null or jobs_attempt < run_attempt then 1 else 0 end), 0) as pending')
            ->first();

        return $this->flakyJobSummaries[$key] = [
            'jobs' => $jobs,
            'flaky' => $flaky,
            'runs' => $runs->count(),
            'seconds' => (int) $jobs->sum('seconds'),
            'waitSeconds' => (int) $runs->groupBy('run_id')->sum($waited),
            'pendingRuns' => (int) $reruns->pending,
            'fetchedRuns' => (int) $reruns->fetched,
        ];
    }

    /**
     * @param  array{workflowId: int, name: string}  $job
     */
    private static function flakyJobKey(array $job): string
    {
        return "{$job['workflowId']}:{$job['name']}";
    }

    /**
     * Whether the default branch is green right now, judged by the latest finished run of every workflow on it.
     *
     * @return array{state: BranchStatus, failingWorkflows: array<int, string>, brokenSince: string|null, lastRunAt: string|null}
     */
    public function defaultBranchStatus(): array
    {
        // The last streak of each workflow holds its latest run; it is still red when nothing passed after it broke.
        $workflows = $this->streaks()
            ->groupBy('workflow_id')
            ->map(fn (Collection $streaks): array => $streaks->last())
            ->sortByDesc(fn (array $streak): int => $streak['last_run_at']->getTimestamp());

        if ($workflows->isEmpty()) {
            return ['state' => BranchStatus::Unknown, 'failingWorkflows' => [], 'brokenSince' => null, 'lastRunAt' => null];
        }

        $failing = $workflows->filter(fn (array $streak): bool => $streak['recovered_at'] === null);

        return [
            'state' => $failing->isEmpty() ? BranchStatus::Passing : BranchStatus::Failing,
            'failingWorkflows' => $failing->pluck('latest_name')->values()->all(),
            'brokenSince' => $failing->min('broken_at')?->toIso8601String(),
            'lastRunAt' => $workflows->first()['last_run_at']->toIso8601String(),
        ];
    }

    /**
     * Run counts and CI seconds for the runs a query matches, in one pass.
     *
     * @param  Builder<WorkflowRun>  $runs
     */
    private function counts(Builder $runs): QueryBuilder
    {
        [$defaultBranch, $bindings] = $this->defaultBranchCondition();
        $decisive = WorkflowRun::DecisiveCondition;
        $failed = WorkflowRun::FailedCondition;
        $pullRequest = "substr(event, 1, 12) = 'pull_request'";

        return $runs->toBase()->selectRaw(implode(', ', [
            'count(*) as runs',
            'coalesce(sum('.WorkflowRun::DurationExpression.'), 0) as seconds',
            "sum(case when {$decisive} then 1 else 0 end) as decisive",
            "sum(case when {$failed} then 1 else 0 end) as failed",
            "sum(case when {$decisive} and {$defaultBranch} then 1 else 0 end) as default_branch",
            "sum(case when {$failed} and {$defaultBranch} then 1 else 0 end) as default_branch_failed",
            "sum(case when {$decisive} and {$pullRequest} then 1 else 0 end) as pull_request",
            "sum(case when {$failed} and {$pullRequest} then 1 else 0 end) as pull_request_failed",
            "sum(case when {$decisive} and run_attempt > 1 then 1 else 0 end) as reruns",
        ]), [...$bindings, ...$bindings]);
    }

    /**
     * Share of commits whose workflow both failed and passed, or only passed after a retry.
     */
    private function flakyRate(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $commits = $this->flakyCommits($this->runs($from, $to))->first();

        return Stats::ratio((int) $commits->flaky, (int) $commits->commits);
    }

    /**
     * Counts commits and flaky commits, one row per workflow and commit underneath.
     *
     * @param  Builder<WorkflowRun>  $runs
     * @param  string|null  $groupedBy  A column to keep from the per-commit rows, so the caller can group by it.
     */
    private function flakyCommits(Builder $runs, ?string $groupedBy = null): QueryBuilder
    {
        $failed = WorkflowRun::FailedCondition;

        $commits = $runs->decisive()
            ->toBase()
            ->select($groupedBy === null ? [] : [$groupedBy])
            ->selectRaw(implode(', ', [
                "max(case when {$failed} then 1 else 0 end) as failed",
                "max(case when {$failed} then 0 else 1 end) as passed",
                "max(case when not {$failed} and run_attempt > 1 then 1 else 0 end) as retried_pass",
            ]))
            ->groupBy('workflow_id', 'head_sha');

        return $this->repository->getConnection()->query()
            ->fromSub($commits, 'commits')
            ->selectRaw('count(*) as commits, coalesce(sum(case when passed = 1 and (failed = 1 or retried_pass = 1) then 1 else 0 end), 0) as flaky');
    }

    /**
     * Share of working time in the window during which any workflow on the default branch was failing.
     */
    private function redShare(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        [$defaultBranch, $bindings] = $this->defaultBranchCondition();

        if ($this->defaultBranch === null || ! $this->repository->measuredWorkflowRuns()->where('created_at', '>=', $this->since)->whereRaw($defaultBranch, $bindings)->exists()) {
            return null;
        }

        $red = 0;

        foreach ($this->redIntervals() as [$start, $end]) {
            $start = max($start, $from->getTimestamp());
            $end = min($end, $to->getTimestamp());

            if ($end > $start) {
                $red += $this->time->between(CarbonImmutable::createFromTimestamp($start), CarbonImmutable::createFromTimestamp($end));
            }
        }

        return Stats::ratio($red, $this->time->between($from, $to));
    }

    /**
     * Merged intervals (unix timestamps) during which the default branch was red.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function redIntervals(): array
    {
        $intervals = $this->streaks()
            ->filter(fn (array $streak): bool => $streak['broken_at'] !== null)
            ->map(fn (array $streak): array => [$streak['broken_at']->getTimestamp(), ($streak['recovered_at'] ?? $this->now)->getTimestamp()])
            ->sortBy(fn (array $interval): int => $interval[0])
            ->values()
            ->all();

        $merged = [];

        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * How long the default branch stayed red, for every recovery within the window.
     *
     * @return Collection<int, int>
     */
    private function recoveryTimes(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->streaks()
            ->filter(fn (array $streak): bool => $streak['broken_at'] !== null && $streak['recovered_at'] !== null
                && $streak['recovered_at']->gte($from) && $streak['recovered_at']->lt($to))
            ->map(fn (array $streak): int => $this->time->between($streak['broken_at'], $streak['recovered_at']))
            ->values();
    }

    /**
     * Every failure streak on the default branch, per workflow and in order, and the latest streak of
     * each workflow, which holds its latest run.
     *
     * Decisive runs are numbered by how many passes came before them, so a run of failures and the
     * pass that ends it share a number. A streak that starts with a pass never broke. Each pass after a
     * pass is a streak of its own, so on a busy repository nearly every run is one; those are left out
     * in the database, as reading one row per run runs a worker out of memory.
     *
     * @return Collection<int, Streak>
     */
    private function streaks(): Collection
    {
        if ($this->streaks !== null) {
            return $this->streaks;
        }

        if ($this->defaultBranch === null) {
            return $this->streaks = collect();
        }

        [$defaultBranch, $bindings] = $this->defaultBranchCondition();

        $runs = $this->repository->measuredWorkflowRuns()
            ->decisive()
            ->where('created_at', '>=', $this->since)
            ->whereRaw($defaultBranch, $bindings)
            ->toBase()
            ->select(['workflow_id', 'created_at', 'completed_at'])
            ->selectRaw('case when '.WorkflowRun::FailedCondition.' then 1 else 0 end as failed')
            ->selectRaw('first_value(name) over (partition by workflow_id order by created_at desc, id desc) as latest_name')
            ->selectRaw('sum(case when '.WorkflowRun::FailedCondition.' then 0 else 1 end) over (partition by workflow_id order by created_at, id rows unbounded preceding) as passes');

        $date = fn (?string $value): ?CarbonImmutable => $value === null ? null : CarbonImmutable::parse($value);

        $numbered = $this->repository->getConnection()->query()
            ->fromSub($runs, 'runs')
            ->select(['workflow_id', 'created_at', 'completed_at', 'failed', 'latest_name'])
            // Passes up to and including the run, minus the run itself when it passed.
            ->selectRaw('passes - (1 - failed) as streak')
            ->selectRaw('max(passes - (1 - failed)) over (partition by workflow_id) as last_streak');

        return $this->streaks = $this->repository->getConnection()->query()
            ->fromSub($numbered, 'numbered')
            ->selectRaw(implode(', ', [
                'workflow_id',
                'max(latest_name) as latest_name',
                'max(created_at) as last_run_at',
                'min(case when failed = 1 then created_at end) as broken_at',
                'max(case when failed = 0 then coalesce(completed_at, created_at) end) as recovered_at',
            ]))
            ->groupBy('workflow_id', 'streak')
            ->havingRaw('min(case when failed = 1 then created_at end) is not null or streak = max(last_streak)')
            ->orderByRaw('workflow_id, min(created_at)')
            ->get()
            ->map(fn (object $row): array => [
                'workflow_id' => (int) $row->workflow_id,
                'latest_name' => (string) $row->latest_name,
                'last_run_at' => CarbonImmutable::parse($row->last_run_at),
                'broken_at' => $date($row->broken_at),
                'recovered_at' => $date($row->recovered_at),
            ]);
    }

    /**
     * Runs created within the window, leaving out excluded workflows unless asked to keep them.
     *
     * @return Builder<WorkflowRun>
     */
    private function runs(CarbonImmutable $from, CarbonImmutable $to, bool $withExcluded = false): Builder
    {
        $runs = $withExcluded ? $this->repository->workflowRuns() : $this->repository->measuredWorkflowRuns();

        return $runs->getQuery()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to);
    }

    /**
     * SQL condition for runs on the default branch outside pull requests, with its bindings.
     *
     * @return array{0: literal-string, 1: list<string>}
     */
    private function defaultBranchCondition(): array
    {
        return $this->defaultBranch === null
            ? ['(1 = 0)', []]
            : ["(head_branch = ? and substr(event, 1, 12) <> 'pull_request')", [$this->defaultBranch]];
    }

    /**
     * SQL expression for how many whole hours after a moment a run started. Binds the moment (unix
     * seconds). Buckets start at midnight, a whole number of hours after the first one in every
     * timezone but Lord Howe Island's, whose clocks move by half an hour; there, runs in the half
     * hour around a midnight after the change can land on the neighbouring day.
     *
     * @return literal-string
     */
    private function hourExpression(): string
    {
        return match ($driver = $this->repository->getConnection()->getDriverName()) {
            'pgsql' => 'floor((extract(epoch from created_at) - ?) / 3600)',
            // Integer division already floors, as runs are never before the charts start.
            'sqlite' => "((cast(strftime('%s', created_at) as integer) - ?) / 3600)",
            'mysql', 'mariadb' => 'floor((unix_timestamp(created_at) - ?) / 3600)',
            default => throw new LogicException("Unsupported database driver [{$driver}]."),
        };
    }
}
