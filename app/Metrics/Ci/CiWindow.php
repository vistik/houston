<?php

namespace App\Metrics\Ci;

use App\Metrics\CiFailureRateScope;
use Closure;
use Illuminate\Support\Collection;

/**
 * GitHub Actions workflow runs created within a window, leaving out excluded workflows. Every query
 * runs at most once, however many metrics read it.
 *
 * Decisive runs are those that finished with a success or a failure; cancelled and skipped runs don't count.
 */
final class CiWindow
{
    private ?object $counts = null;

    /** @var array<string, Collection<int, int>> */
    private array $durations = [];

    /** @var Collection<int, int>|null */
    private ?Collection $commitDurations = null;

    /** @var Collection<int, int>|null */
    private ?Collection $recoveryTimes = null;

    private bool $flakyRateResolved = false;

    private ?float $flakyRate = null;

    private bool $redShareResolved = false;

    private ?float $redShare = null;

    /** @var array{runs: int, seconds: int, waitSeconds: int, pendingRuns: int, fetchedRuns: int}|null */
    private ?array $flakyJobs = null;

    /**
     * @param  CiFailureRateScope  $failureRateScope  Which runs the CI failure rate counts.
     * @param  Closure(): object  $makeCounts
     * @param  Closure(string): Collection<int, int>  $makeDurations
     * @param  Closure(): Collection<int, int>  $makeCommitDurations
     * @param  Closure(): Collection<int, int>  $makeRecoveryTimes
     * @param  Closure(): ?float  $makeFlakyRate
     * @param  Closure(): ?float  $makeRedShare
     * @param  Closure(): array{runs: int, seconds: int, waitSeconds: int, pendingRuns: int, fetchedRuns: int}  $makeFlakyJobs
     */
    public function __construct(
        public readonly CiFailureRateScope $failureRateScope,
        private Closure $makeCounts,
        private Closure $makeDurations,
        private Closure $makeCommitDurations,
        private Closure $makeRecoveryTimes,
        private Closure $makeFlakyRate,
        private Closure $makeRedShare,
        private Closure $makeFlakyJobs,
    ) {}

    /**
     * Run counts within the window, by column:
     *
     * - runs: every run
     * - seconds: CI seconds of every run
     * - decisive, failed: decisive runs, and those that failed
     * - default_branch, default_branch_failed: decisive runs on the default branch outside pull requests, and those that failed
     * - pull_request, pull_request_failed: decisive runs for pull request events, and those that failed
     * - reruns: decisive runs that were a second or later attempt
     */
    public function count(string $column): int
    {
        $this->counts ??= ($this->makeCounts)();

        return (int) $this->counts->{$column};
    }

    /**
     * Wall-clock seconds of every decisive run, by column: duration_seconds (start to finish) or
     * queue_seconds (created to started).
     *
     * @return Collection<int, int>
     */
    public function durations(string $column): Collection
    {
        return $this->durations[$column] ??= ($this->makeDurations)($column);
    }

    /**
     * Wall-clock seconds per commit, taken from its slowest decisive run, so quick automation
     * (labelers, triage) doesn't drown out the real CI.
     *
     * @return Collection<int, int>
     */
    public function commitDurations(): Collection
    {
        return $this->commitDurations ??= ($this->makeCommitDurations)();
    }

    /**
     * Working time from a workflow breaking on the default branch to it passing again, for every
     * recovery within the window.
     *
     * @return Collection<int, int>
     */
    public function recoveryTimes(): Collection
    {
        return $this->recoveryTimes ??= ($this->makeRecoveryTimes)();
    }

    /**
     * Share of commits whose workflow both failed and passed, or only passed after a retry, counted per workflow and commit.
     */
    public function flakyRate(): ?float
    {
        if (! $this->flakyRateResolved) {
            $this->flakyRate = ($this->makeFlakyRate)();
            $this->flakyRateResolved = true;
        }

        return $this->flakyRate;
    }

    /**
     * Share of working time in the window during which any workflow on the default branch was failing.
     */
    public function redShare(): ?float
    {
        if (! $this->redShareResolved) {
            $this->redShare = ($this->makeRedShare)();
            $this->redShareResolved = true;
        }

        return $this->redShare;
    }

    /**
     * What the jobs that failed and then passed on a re-run cost, by key:
     *
     * - runs: how often a job did, counted per run and job
     * - seconds: CI seconds of their failed attempts
     * - waitSeconds: working time from a failed attempt finishing to the re-run passing, each run once
     * - pendingRuns, fetchedRuns: re-runs whose jobs are still to be fetched, and those that are
     */
    public function flakyJobs(string $key): int
    {
        return ($this->flakyJobs ??= ($this->makeFlakyJobs)())[$key];
    }

    /**
     * Whether the window's flaky jobs are known: there were no re-runs, or some have had their jobs fetched.
     */
    public function flakyJobsKnown(): bool
    {
        return $this->flakyJobs('pendingRuns') === 0 || $this->flakyJobs('fetchedRuns') > 0;
    }
}
