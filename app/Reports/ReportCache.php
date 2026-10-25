<?php

namespace App\Reports;

use App\Metrics\Benchmarks;
use App\Metrics\CiFailureRateScope;
use App\Metrics\Granularity;
use App\Metrics\Metrics;
use App\Metrics\Period;
use App\Metrics\Trends;
use App\Models\Repository;
use App\Models\RepositorySync;
use App\Models\Team;
use App\Settings\ReportDefaults;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Memoizes computed velocity reports.
 *
 * Each report is kept under a key for its kind, repository, period (length or range, granularity and
 * whether the current bucket is left out) and whether bots are included, together with which runs the
 * CI failure rate counts, the timezone it is shown in and the moment the repository was last synced or
 * had its settings changed. It is served until any of those moves on, so changing the CI failure rate
 * or timezone in the report defaults, a finished sync or a settings change invalidates it and the next
 * one replaces it. Reports expire a week after they were built anyway, so one-off custom ranges don't
 * pile up.
 * Benchmark ratings and metric descriptions are applied after the cache, so changing either takes
 * effect immediately.
 *
 * Team reports read every repository, so any finished sync, watched repository or change to who was on
 * the team invalidates them. They are built when first opened rather than warmed after each sync.
 */
class ReportCache
{
    private const int UnusedDays = 7;

    /**
     * Bump this when a report gains, loses or changes a field, so reports cached before the change are
     * rebuilt instead of served without it until the repository syncs again.
     */
    private const int Format = 10;

    public function __construct(private Benchmarks $benchmarks) {}

    /**
     * Periods whose repository report is warmed for every grouping that fits them.
     *
     * @var list<int>
     */
    private const array WarmedDays = [7, 14, 30, 45, 90, 180, 365];

    /**
     * Compute the reports people are likely to open, so the first view after a sync is instant. What
     * pages and tools open when nothing is picked comes first: the overview and repository page with
     * the report defaults, and both walls. Then the repository report for each of the WarmedDays in
     * each grouping, leaving out the current bucket as the defaults do and without bots, and the MCP
     * tools' 30 days.
     */
    public function warm(Repository $repository): void
    {
        $repository->load('sync');
        $defaults = ReportDefaults::current();

        $this->summary($repository, Period::fromRequest(new Request, Repository::longestLookbackDays(), $defaults));
        $this->full($repository, Period::fromRequest(new Request, $repository->lookbackDays(), $defaults)->withoutCurrentBucket($defaults->withoutCurrentBucket));
        $this->wall($repository, Period::lastDays(14, $defaults->timezone));
        $this->full($repository, Period::lastDays(30, $defaults->timezone));

        foreach (self::WarmedDays as $days) {
            foreach (Granularity::cases() as $granularity) {
                $period = Period::lastDays($days, $defaults->timezone)->groupedBy($granularity);

                // Too short to chart this way, so it would fall back to a grouping already warmed.
                if ($period->bucketGranularity() !== $granularity) {
                    continue;
                }

                $this->full($repository, $period->withoutCurrentBucket($defaults->withoutCurrentBucket));
            }
        }

        $this->summary($repository, Period::lastDays(30, $defaults->timezone));
    }

    /**
     * @return array<string, mixed>
     */
    public function full(Repository $repository, Period $period, bool $includeBots = false): array
    {
        return Trends::describe(Metrics::describe($this->benchmarks->apply($this->remember(ReportKind::Full, $repository, $period, $includeBots), $period)));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Repository $repository, Period $period): array
    {
        return Metrics::describe($this->benchmarks->apply($this->remember(ReportKind::Summary, $repository, $period, false), $period));
    }

    /**
     * @return array<string, mixed>
     */
    public function wall(Repository $repository, Period $period): array
    {
        return Metrics::describe($this->benchmarks->apply($this->remember(ReportKind::Wall, $repository, $period, false), $period));
    }

    /**
     * @return array<string, mixed>
     */
    public function team(Team $team, Period $period, ?Repository $onlyRepository = null): array
    {
        return Trends::describe(Metrics::describe($this->benchmarks->apply($this->rememberTeam(ReportKind::Full, $team, $period, $onlyRepository), $period)), Trends::PullRequestTrends);
    }

    /**
     * How many pull requests the team opened or merged in the period, per repository by owner/name.
     *
     * @return array<string, int>
     */
    public function teamPullRequestsPerRepository(Team $team, Period $period): array
    {
        $counts = $this->rememberTeam(ReportKind::Full, $team, $period)['repositories'] ?? [];

        return is_array($counts) ? array_map(intval(...), $counts) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function teamSummary(Team $team, Period $period): array
    {
        return Metrics::describe($this->benchmarks->apply($this->rememberTeam(ReportKind::Summary, $team, $period), $period));
    }

    /**
     * @return array<string, mixed>
     */
    private function remember(ReportKind $kind, Repository $repository, Period $period, bool $includeBots): array
    {
        $syncedAt = $repository->sync?->finished_at?->getTimestamp() ?? 0;
        $changedAt = $repository->updated_at?->getTimestamp() ?? 0;
        $scope = CiFailureRateScope::current();

        return $this->cached(
            "houston:report:{$kind->value}:{$repository->id}:{$period->key()}:".(int) $includeBots,
            self::Format.":{$scope->value}:{$period->timezone}:{$syncedAt}:{$changedAt}",
            fn (): array => $kind->build(new VelocityReport($repository, $period, $includeBots, ciFailureRateScope: $scope)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rememberTeam(ReportKind $kind, Team $team, Period $period, ?Repository $onlyRepository = null): array
    {
        $syncedAt = RepositorySync::query()->max('finished_at');
        $repositories = Repository::query()->toBase()->selectRaw('count(*) as count, max(updated_at) as changed_at')->first();
        $version = implode(':', [self::Format, $period->timezone, $syncedAt, $repositories?->count, $repositories?->changed_at, $team->updated_at?->getTimestamp()]);

        return $this->cached(
            "houston:report:team-{$kind->value}:{$team->id}:{$period->key()}:".($onlyRepository->id ?? 'all'),
            $version,
            fn (): array => $kind->build(new VelocityReport($team, $period, onlyRepository: $onlyRepository)),
        );
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function cached(string $key, string $version, Closure $build): array
    {
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            return $cached['report'];
        }

        $report = $build();

        Cache::put($key, ['version' => $version, 'report' => $report], now()->addDays(self::UnusedDays));

        return $report;
    }
}
