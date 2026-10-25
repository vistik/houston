<?php

namespace App\Metrics;

use App\Metrics\Ci\CiWindow;
use App\Models\Repository;
use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use LogicException;

/**
 * CI signals from GitHub Actions workflow runs, computed in the database.
 *
 * Busy repositories have tens of thousands of runs, so everything that can be counted, summed or
 * grouped happens in SQL. Only medians (which SQLite can't compute) and working-time durations are
 * finished in PHP, from small result sets.
 *
 * @phpstan-type Streak array{workflow_id: int, latest_name: string, last_run_at: CarbonImmutable, broken_at: CarbonImmutable|null, recovered_at: CarbonImmutable|null}
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
     * @param  CarbonImmutable  $since  How far back runs are considered, e.g. the start of the previous period.
     * @param  CiFailureRateScope  $failureRateScope  Which runs the CI failure rate and its trend count.
     */
    public function __construct(
        private Repository $repository,
        private ?string $defaultBranch,
        private CarbonImmutable $since,
        private WorkingTime $time,
        private CarbonImmutable $now,
        private CiFailureRateScope $failureRateScope = CiFailureRateScope::DefaultBranch,
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
     * Every failure streak on the default branch, per workflow and in order.
     *
     * Decisive runs are numbered by how many passes came before them, so a run of failures and the
     * pass that ends it share a number. A streak that starts with a pass never broke.
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

        return $this->streaks = $this->repository->getConnection()->query()
            ->fromSub($runs, 'runs')
            ->selectRaw(implode(', ', [
                'workflow_id',
                'max(latest_name) as latest_name',
                'max(created_at) as last_run_at',
                'min(case when failed = 1 then created_at end) as broken_at',
                'max(case when failed = 0 then coalesce(completed_at, created_at) end) as recovered_at',
            ]))
            // Passes up to and including the run, minus the run itself when it passed.
            ->groupByRaw('workflow_id, passes - (1 - failed)')
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
