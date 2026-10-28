<?php

namespace App\Reports;

use App\Enums\PullRequestState;
use App\Metrics\BranchStatus;
use App\Metrics\Ci\CiJob;
use App\Metrics\Ci\CiWindow;
use App\Metrics\CiFailureRateScope;
use App\Metrics\CiMetrics;
use App\Metrics\Delivery\DeliveryMetric;
use App\Metrics\Delivery\DeliveryWindow;
use App\Metrics\DeliveryMetrics;
use App\Metrics\Distribution;
use App\Metrics\Metric;
use App\Metrics\Metrics;
use App\Metrics\OpenPullRequestStatus;
use App\Metrics\Period;
use App\Metrics\PullRequests\PullRequestMetric;
use App\Metrics\PullRequests\PullRequestWindow;
use App\Metrics\ReviewFlow;
use App\Metrics\ReviewStage;
use App\Metrics\SizeBucket;
use App\Metrics\Snapshot\Snapshot;
use App\Metrics\Snapshot\SnapshotMetric;
use App\Metrics\Statistic;
use App\Metrics\Stats;
use App\Metrics\Timeline;
use App\Metrics\Window;
use App\Metrics\WorkingTime;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Computes every velocity signal for a repository or a team over a period, read entirely from the local cache.
 *
 * A team report covers the pull requests its members authored across every watched repository, and only
 * the pull request metrics: CI runs and deployments belong to repositories rather than people. Each thing
 * that happens to a pull request counts for the team its author was on at that moment, so moving someone
 * to another team keeps their earlier work with the team they did it for.
 *
 * @phpstan-type PullRequestSlice array{merged: Collection<int, PullRequest>, opened: Collection<int, PullRequest>, abandoned: Collection<int, PullRequest>, pickedUp: Collection<int, PullRequest>, approved: Collection<int, PullRequest>, reviewers: Closure(): Collection<int, string>}
 */
class VelocityReport
{
    /**
     * The headline metrics on the overview page.
     *
     * @var list<string>
     */
    public const array SummaryKpis = ['throughput', 'opened', 'cycleTime', 'pickupTime', 'ciFailureRate', 'openPullRequests', 'stalePullRequests', 'deployments', 'changeFailureRate'];

    /** @var Collection<int, PullRequest> */
    private Collection $pullRequests;

    private ?Repository $repository;

    /**
     * When each member was on the team, by login in lower case, or null for a repository report.
     *
     * @var array<string, list<array{from: int|null, until: int|null}>>|null
     */
    private ?array $members;

    private ?string $defaultBranch;

    /**
     * The default branch of every repository the pull requests come from, by repository id.
     *
     * @var array<int, string|null>
     */
    private array $defaultBranches;

    /** @var array<int, ReviewFlow|null> */
    private array $flows = [];

    /**
     * Working-time durations by "from:to" timestamps. The same pull request is measured for every
     * window, bucket and breakdown it appears in.
     *
     * @var array<string, int>
     */
    private array $durations = [];

    /** @var Collection<int, array{reviewer: string, at: int}>|null */
    private ?Collection $peerReviews = null;

    /**
     * Reviews and activities by pull request id, loaded only for the pull requests that need them.
     *
     * @var array<int, Timeline>
     */
    private array $timelines = [];

    private WorkingTime $time;

    private ?DeliveryMetrics $delivery = null;

    private ?CiMetrics $ci = null;

    /**
     * How many pull requests were open at the end of each trend bucket, computed once per report.
     *
     * @var array{ready: array<int, int>, draft: array<int, int>}|null
     */
    private ?array $openPerBucket = null;

    private CarbonImmutable $now;

    /**
     * @param  Repository|null  $onlyRepository  For a team, report only on its pull requests in this repository.
     * @param  CiFailureRateScope  $ciFailureRateScope  Which workflow runs the CI failure rate counts.
     * @param  CiJob|null  $job  One job to narrow the flaky-job metrics to.
     */
    public function __construct(
        Repository|Team $subject,
        private Period $period,
        private bool $includeBots = false,
        ?WorkingTime $time = null,
        private ?Repository $onlyRepository = null,
        private CiFailureRateScope $ciFailureRateScope = CiFailureRateScope::DefaultBranch,
        ?CiJob $job = null,
    ) {
        $this->time = $time ?? WorkingTime::in($period->timezone);
        $this->now = CarbonImmutable::now();
        $this->repository = $subject instanceof Repository ? $subject : null;
        $this->members = $subject instanceof Team ? $subject->loadMissing('memberships')->spans() : null;
        $this->defaultBranch = $this->repository?->sync?->default_branch;
        $this->pullRequests = $this->loadPullRequests();
        $this->defaultBranches = $this->repository !== null
            ? [$this->repository->id => $this->defaultBranch]
            : $this->pullRequests->pluck('repository')->unique('id')->mapWithKeys(fn (Repository $repository): array => [$repository->id => $repository->sync?->default_branch])->all();

        if ($this->repository === null) {
            return;
        }

        $this->ci = new CiMetrics($this->repository, $this->defaultBranch, $period->previousFrom, $this->time, $this->now, $ciFailureRateScope, $job);

        // Load one extra month so the first deployment in the window knows what it shipped.
        $this->delivery = new DeliveryMetrics(
            $this->repository->deployments()->where('deployed_at', '>=', $period->previousFrom->subDays(30))->get(),
            $this->pullRequests,
            $this->defaultBranch,
            $this->time,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $report = [
            'period' => [
                'days' => $this->period->days,
                'custom' => $this->period->custom,
                'from' => $this->period->local($this->period->from)->toIso8601String(),
                'to' => $this->period->local($this->period->to)->toIso8601String(),
                'bucketDays' => $this->period->bucketDays(),
                'granularity' => $this->period->bucketGranularity(),
            ],
            'kpis' => $this->kpis(),
            'cycleBreakdown' => $this->cycleBreakdown(),
            'cycleDistribution' => $this->cycleDistribution(),
            'trend' => $this->trend(),
            'series' => $this->series(),
            'sizes' => $this->sizeDistribution(),
            'workInProgress' => $this->workInProgress(),
            'slowestMerged' => $this->slowestMerged(),
            'remediations' => $this->remediations(),
            'workingTime' => $this->time->label(),
        ];

        if ($this->repository === null) {
            return [...$report, 'repositories' => $this->pullRequestsPerRepository()];
        }

        return [
            ...$report,
            'workflows' => $this->workflows(),
            'flakyJobs' => $this->ci()->flakyJobs($this->period),
            'ciFailureRateScope' => $this->ciFailureRateScope,
            'defaultBranchStatus' => $this->defaultBranchStatus(),
            'delivery' => $this->deliverySummary(),
            'botBacklog' => $this->botBacklog(),
        ];
    }

    /**
     * A compact subset for the overview page.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $merged = array_fill(0, count($this->period->buckets()), 0);

        foreach ($this->pullRequests as $pr) {
            if ($this->period->charts($mergedAt = $this->attributed($pr, $pr->merged_at))) {
                $merged[$this->period->bucketIndex($mergedAt)]++;
            }
        }

        $summary = [
            'period' => [
                'to' => $this->period->local($this->period->to)->toIso8601String(),
                'bucketDays' => $this->period->bucketDays(),
                'granularity' => $this->period->bucketGranularity(),
            ],
            'kpis' => $this->kpis(self::SummaryKpis),
            'trend' => array_map(fn (CarbonImmutable $start, int $count): array => ['date' => $start->toDateString(), 'merged' => $count], $this->period->buckets(), $merged),
            'series' => $this->series(self::SummaryKpis),
        ];

        return $this->repository === null ? $summary : [...$summary, 'defaultBranchStatus' => $this->defaultBranchStatus()];
    }

    /**
     * What the wall display needs: headline numbers, the trend, and what needs attention right now.
     *
     * @return array<string, mixed>
     */
    public function wall(): array
    {
        if ($this->repository === null) {
            throw new LogicException('Only repositories have a wall.');
        }

        return [
            'kpis' => collect($this->kpis())->only([
                'cycleTime', 'pickupTime', 'reviewTime', 'throughput', 'opened',
                'ciFailureRate', 'ciDefaultBranchFailureRate', 'ciDuration',
                'openPullRequests', 'stalePullRequests', 'waitingOnAuthor', 'waitingOnReviewer',
                'deployments', 'deployFrequency', 'leadTimeToProduction', 'changeFailureRate',
            ])->all(),
            'trend' => $this->trend(),
            'cycleBreakdown' => $this->cycleBreakdown(),
            'ciFailureRateScope' => $this->ciFailureRateScope,
            'defaultBranchStatus' => $this->defaultBranchStatus(),
            // Stale pull requests are counted separately; the list is for work someone can pick up today.
            'waitingForReview' => collect($this->workInProgress())
                ->filter(fn (array $pullRequest): bool => $pullRequest['status']->needsReview())
                ->where('isStale', false)
                ->sortByDesc('waitingSeconds')
                ->take(5)
                ->values()
                ->all(),
            'delivery' => $this->deliverySummary(),
            'workingTime' => $this->time->label(),
        ];
    }

    /**
     * @return Collection<int, PullRequest>
     */
    private function loadPullRequests(): Collection
    {
        $since = $this->period->previousFrom;

        $query = $this->repository?->pullRequests()->getQuery()
            ?? PullRequest::query()
                ->with('repository.sync')
                ->whereIn(DB::raw('lower(author)'), array_keys($this->members ?? []))
                ->when($this->onlyRepository, fn (Builder $query, Repository $repository) => $query->where('repository_id', $repository->id));

        // Reviews and activities are loaded separately, and only where needed; see timelines().
        $pullRequests = $query
            ->when(! $this->includeBots, fn (Builder $query) => $query->where('author_is_bot', false))
            ->where(fn (Builder $query) => $query
                ->where('state', PullRequestState::Open)
                ->orWhere('opened_at', '>=', $since)
                ->orWhere('merged_at', '>=', $since)
                ->orWhere('closed_at', '>=', $since)
                ->orWhere('first_response_at', '>=', $since)
                ->orWhere('first_approval_at', '>=', $since))
            ->get();

        if ($this->repository !== null) {
            $pullRequests->each->setRelation('repository', $this->repository);
        }

        return $pullRequests;
    }

    /**
     * The moment, if it counts for this report: always for a repository, and for a team only while the
     * author of the pull request was on it.
     */
    private function attributed(PullRequest $pr, ?CarbonImmutable $moment): ?CarbonImmutable
    {
        if ($moment === null || $this->members === null) {
            return $moment;
        }

        $timestamp = $moment->getTimestamp();

        foreach ($this->spans($pr) as $span) {
            if (($span['from'] === null || $timestamp >= $span['from']) && ($span['until'] === null || $timestamp < $span['until'])) {
                return $moment;
            }
        }

        return null;
    }

    /**
     * When the pull request counts for this report, as unix timestamps: always for a repository, and for
     * a team while its author was on it.
     *
     * @return list<array{from: int|null, until: int|null}>
     */
    private function spans(PullRequest $pr): array
    {
        if ($this->members === null) {
            return [['from' => null, 'until' => null]];
        }

        return $this->members[Str::lower((string) $pr->author)] ?? [];
    }

    /**
     * The pull requests open right now that count for this report.
     *
     * @return Collection<int, PullRequest>
     */
    private function openNow(): Collection
    {
        return $this->pullRequests->filter(fn (PullRequest $pr): bool => $pr->state === PullRequestState::Open && $this->attributed($pr, $this->now) !== null);
    }

    /**
     * Load the timelines of the given pull requests, in two queries, if not loaded yet.
     *
     * @template TCollection of Collection<int, PullRequest>
     *
     * @param  TCollection  $pullRequests
     * @return TCollection
     */
    private function timelines(Collection $pullRequests): Collection
    {
        $missing = [];

        foreach ($pullRequests as $pr) {
            if (! isset($this->timelines[$pr->id])) {
                $missing[] = $pr;
            }
        }

        $this->timelines += Timeline::load($missing);

        return $pullRequests;
    }

    private function timeline(PullRequest $pr): Timeline
    {
        return $this->timelines[$pr->id] ?? throw new LogicException("The timeline of pull request #{$pr->number} has not been loaded.");
    }

    /**
     * Headline metrics for the current and previous period. Metrics that sum up one value per item
     * also carry the statistic their value is, and both the median and the average of those values in
     * the current period.
     *
     * @param  list<string>|null  $only  The metrics to compute, or null for all of them.
     * @return array<string, array{value: float|int|null, previous: float|int|null, unit: string, better: string, snapshot?: true, statistic?: string, median?: float|null, average?: float|null}>
     */
    private function kpis(?array $only = null): array
    {
        $current = $this->window(fn (?CarbonImmutable $moment): bool => $this->period->contains($moment), $this->period->from, $this->period->to);
        $previous = $this->window(fn (?CarbonImmutable $moment): bool => $this->period->previousContains($moment), $this->period->previousFrom, $this->period->from);
        $kpis = [];

        foreach (Metrics::all() as $key => $metric) {
            if ($only !== null && ! in_array($key, $only, true)) {
                continue;
            }

            if ($this->repository === null && ! $metric instanceof PullRequestMetric && ! $metric instanceof SnapshotMetric) {
                continue;
            }

            $values = $metric instanceof Distribution ? $metric->values($current) : null;

            $kpis[$key] = [
                'value' => $values !== null ? $metric->statistic()->of($values) : $metric->calculate($current),
                'previous' => $metric instanceof SnapshotMetric ? null : $metric->calculate($previous),
                'unit' => $metric->unit()->value,
                'better' => $metric->better()->value,
            ];

            // Right-now metrics say so, since there is nothing before them to compare with.
            if ($metric instanceof SnapshotMetric) {
                $kpis[$key]['snapshot'] = true;
            }

            if ($values !== null) {
                $kpis[$key] += [
                    'statistic' => $metric->statistic()->value,
                    'median' => Statistic::Median->of($values),
                    'average' => Statistic::Average->of($values),
                ];
            }
        }

        return $kpis;
    }

    /**
     * Everything the metrics of a window are calculated from.
     *
     * @param  callable(?CarbonImmutable): bool  $inWindow
     */
    private function window(callable $inWindow, CarbonImmutable $from, CarbonImmutable $to): Window
    {
        $days = $this->days($from, $to);

        return new Window(
            makePullRequests: fn (): PullRequestWindow => $this->pullRequestWindow($this->slice($inWindow), $days),
            makeCi: $this->ci === null ? null : fn (): CiWindow => $this->ci()->window($from, $to),
            makeDelivery: $this->delivery === null ? null : fn (): DeliveryWindow => $this->delivery()->window($inWindow, $days),
            makeSnapshot: fn (): Snapshot => $this->snapshot(),
        );
    }

    /**
     * @param  PullRequestSlice  $slice
     */
    private function pullRequestWindow(array $slice, int $days): PullRequestWindow
    {
        return new PullRequestWindow(
            merged: $slice['merged'],
            opened: $slice['opened'],
            abandoned: $slice['abandoned'],
            pickedUp: $slice['pickedUp'],
            approved: $slice['approved'],
            days: $days,
            defaultBranches: $this->defaultBranches,
            makeReviewers: $slice['reviewers'],
            seconds: $this->seconds(...),
            loadTimelines: $this->timelines(...),
            timeline: $this->timeline(...),
            flow: $this->flow(...),
        );
    }

    private function snapshot(): Snapshot
    {
        $open = $this->openNow();

        return new Snapshot($open, fn (): Collection => $this->timelines($open)->filter(fn (PullRequest $pr): bool => $this->isStale($pr)));
    }

    /**
     * Every pull request and delivery metric per trend bucket, and how many pull requests were open
     * at the end of each, so each headline number can also be read over time. CI metrics are left out,
     * since each would take its own queries per bucket; the trend carries CI runs and failure rate.
     *
     * @param  list<string>|null  $only  The metrics to break down, or null for all of them.
     * @return array<string, list<float|int|null>>
     */
    private function series(?array $only = null): array
    {
        $series = [];
        $metrics = array_filter(
            Metrics::all(),
            fn (Metric $metric, string $key): bool => ($only === null || in_array($key, $only, true))
                && ($metric instanceof PullRequestMetric || ($this->delivery !== null && $metric instanceof DeliveryMetric)),
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($this->bucketWindows() as $window) {
            foreach ($metrics as $key => $metric) {
                $series[$key][] = $metric->calculate($window);
            }
        }

        if ($only === null || in_array('openPullRequests', $only, true)) {
            $open = $this->openPerBucket();
            $series['openPullRequests'] = array_map(fn (int $ready, int $draft): int => $ready + $draft, $open['ready'], $open['draft']);
        }

        return $series;
    }

    /**
     * One metric's headline numbers: its value, the previous period's, and how to read them.
     *
     * @return array<string, mixed>
     */
    public function metric(string $key): array
    {
        return $this->kpis([$key])[$key] ?? throw new LogicException("[{$key}] isn't a metric of this report.");
    }

    /**
     * One metric per trend bucket. For a metric that sums up one value per item, both the median
     * and the average of those values per bucket come with it. CI metrics take their own queries per
     * bucket, which is why the report's series leave CI out; a page about one metric can afford it.
     *
     * @return array{values: list<float|int|null>, median?: list<float|null>, average?: list<float|null>}
     */
    public function metricSeries(string $key): array
    {
        $metric = Metrics::all()[$key] ?? null;

        if ($metric === null || $metric instanceof SnapshotMetric) {
            throw new LogicException("[{$key}] can't be broken down per bucket.");
        }

        $windows = $this->bucketWindows();

        if (! $metric instanceof Distribution) {
            return ['values' => array_map(fn (Window $window): float|int|null => $metric->calculate($window), $windows)];
        }

        $samples = array_map(fn (Window $window): Collection => $metric->values($window), $windows);

        return [
            'values' => array_map(fn (Collection $values): ?float => $metric->statistic()->of($values), $samples),
            'median' => array_map(fn (Collection $values): ?float => Statistic::Median->of($values), $samples),
            'average' => array_map(fn (Collection $values): ?float => Statistic::Average->of($values), $samples),
        ];
    }

    /**
     * Everything the metrics of each trend bucket are calculated from. CI runs are only queried when a
     * metric asks for them.
     *
     * @return list<Window>
     */
    private function bucketWindows(): array
    {
        $starts = $this->period->buckets();
        $ends = array_map(fn (CarbonImmutable $start): CarbonImmutable => $start->addDays($this->period->bucketDays())->min($this->period->to), $starts);
        $days = array_map(fn (CarbonImmutable $start, CarbonImmutable $end): int => $this->days($start, $end), $starts, $ends);
        $deliveries = $this->delivery?->bucketWindows($this->period, $days);
        $slices = $this->bucketSlices();

        return array_map(fn (int $index, array $slice): Window => new Window(
            makePullRequests: fn (): PullRequestWindow => $this->pullRequestWindow($slice, $days[$index]),
            makeCi: $this->ci === null ? null : fn (): CiWindow => $this->ci()->window($starts[$index], $ends[$index]->min($this->period->chartedUntil)),
            makeDelivery: $deliveries === null ? null : fn (): DeliveryWindow => $deliveries[$index],
        ), array_keys($slices), $slices);
    }

    /**
     * The pull requests that moved within a window, by what happened to them, and the peer reviewers
     * active in it.
     *
     * @param  callable(?CarbonImmutable): bool  $inWindow
     * @return PullRequestSlice
     */
    private function slice(callable $inWindow): array
    {
        return [
            'merged' => $this->pullRequests->filter(fn (PullRequest $pr): bool => $inWindow($this->attributed($pr, $pr->merged_at))),
            'opened' => $this->pullRequests->filter(fn (PullRequest $pr): bool => $inWindow($this->attributed($pr, $pr->opened_at))),
            'abandoned' => $this->pullRequests->filter(fn (PullRequest $pr): bool => $this->isAbandoned($pr) && $inWindow($this->attributed($pr, $pr->closed_at))),
            'pickedUp' => $this->pullRequests->filter(fn (PullRequest $pr): bool => $inWindow($this->attributed($pr, $pr->first_response_at))),
            'approved' => $this->pullRequests->filter(fn (PullRequest $pr): bool => $inWindow($this->attributed($pr, $pr->first_approval_at))),
            'reviewers' => fn (): Collection => $this->peerReviews()->filter(fn (array $review): bool => $inWindow(Timeline::moment($review['at'])))->pluck('reviewer'),
        ];
    }

    /**
     * A slice per trend bucket, sorted in one pass over the pull requests instead of one pass per bucket.
     *
     * @return list<PullRequestSlice>
     */
    private function bucketSlices(): array
    {
        $count = count($this->period->buckets());
        $bucket = fn (?CarbonImmutable $moment): ?int => $this->period->charts($moment) ? $this->period->bucketIndex($moment) : null;
        $groups = array_fill_keys(['merged', 'opened', 'abandoned', 'pickedUp', 'approved'], array_fill(0, $count, []));

        foreach ($this->pullRequests as $key => $pr) {
            $moments = [
                'merged' => $pr->merged_at,
                'opened' => $pr->opened_at,
                'abandoned' => $this->isAbandoned($pr) ? $pr->closed_at : null,
                'pickedUp' => $pr->first_response_at,
                'approved' => $pr->first_approval_at,
            ];

            foreach ($moments as $group => $moment) {
                if (($index = $bucket($this->attributed($pr, $moment))) !== null) {
                    $groups[$group][$index][$key] = $pr;
                }
            }
        }

        $reviewers = null;
        $reviewersIn = function (int $index) use (&$reviewers, $count, $bucket): Collection {
            if ($reviewers === null) {
                $reviewers = array_fill(0, $count, []);

                foreach ($this->peerReviews() as $review) {
                    if (($at = $bucket(Timeline::moment($review['at']))) !== null) {
                        $reviewers[$at][] = $review['reviewer'];
                    }
                }
            }

            return collect($reviewers[$index]);
        };

        return array_map(fn (int $index): array => [
            'merged' => new EloquentCollection($groups['merged'][$index]),
            'opened' => new EloquentCollection($groups['opened'][$index]),
            'abandoned' => new EloquentCollection($groups['abandoned'][$index]),
            'pickedUp' => new EloquentCollection($groups['pickedUp'][$index]),
            'approved' => new EloquentCollection($groups['approved'][$index]),
            'reviewers' => fn (): Collection => $reviewersIn($index),
        ], range(0, $count - 1));
    }

    private function isAbandoned(PullRequest $pr): bool
    {
        return $pr->merged_at === null && $pr->state === PullRequestState::Closed;
    }

    /**
     * Every review by someone other than the author and not by a bot, across all pull requests. A team
     * report keeps the reviews of its pull requests made while their author was on it. When each was
     * made is kept as a unix timestamp, as there can be tens of thousands.
     *
     * @return Collection<int, array{reviewer: string, at: int}>
     */
    private function peerReviews(): Collection
    {
        return $this->peerReviews ??= $this->timelines($this->pullRequests)->flatMap(fn (PullRequest $pr): Collection => collect($this->timeline($pr)->reviews)
            ->filter(fn (array $review): bool => $review['reviewer'] !== $pr->author && ! $review['isBot'] && $this->attributed($pr, Timeline::moment($review['at'])) !== null)
            ->map(fn (array $review): array => ['reviewer' => $review['reviewer'], 'at' => $review['at']]))
            ->values();
    }

    private function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(1, (int) round($from->diffInDays($to)));
    }

    private function flow(PullRequest $pr): ?ReviewFlow
    {
        if (! array_key_exists($pr->id, $this->flows)) {
            $this->flows[$pr->id] = ReviewFlow::analyse($pr, $this->timeline($pr), $this->time, $this->now);
        }

        return $this->flows[$pr->id];
    }

    /**
     * Where merged pull requests spend their time, as the average per pull request for each stage.
     *
     * Stages come from replaying each timeline, so they add up to the whole journey from first
     * commit to merge (in working time).
     *
     * @return list<array{stage: string, label: string, seconds: float|null}>
     */
    private function cycleBreakdown(): array
    {
        $merged = $this->timelines($this->mergedInPeriod());
        $flows = $merged->map(fn (PullRequest $pr): ?ReviewFlow => $this->flow($pr))->filter();

        $average = fn (Collection $values): ?float => $values->isEmpty() ? null : round((float) $values->avg());

        return [
            ['stage' => 'coding', 'label' => 'Coding', 'seconds' => $average($merged->filter->first_commit_at->map(fn (PullRequest $pr): int => $this->seconds($pr->first_commit_at, $pr->readyForReviewAt())))],
            ['stage' => 'pickup', 'label' => 'Pickup', 'seconds' => $average($flows->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Pickup)))],
            ['stage' => 'author', 'label' => 'With author', 'seconds' => $average($flows->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Author)))],
            ['stage' => 'review', 'label' => 'Re-review', 'seconds' => $average($flows->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Reviewer)))],
            ['stage' => 'merge', 'label' => 'Merge', 'seconds' => $average($flows->map(fn (ReviewFlow $flow): int => $flow->secondsIn(ReviewStage::Merge)))],
        ];
    }

    /**
     * Histogram of time-to-merge for pull requests merged in the period.
     *
     * @return list<array{label: string, count: int}>
     */
    private function cycleDistribution(): array
    {
        $buckets = ['< 1h' => 3600, '1–4h' => 4 * 3600, '4–24h' => 86400, '1–3d' => 3 * 86400, '3–7d' => 7 * 86400, '1–2w' => 14 * 86400, '> 2w' => PHP_INT_MAX];
        $counts = array_fill_keys(array_keys($buckets), 0);

        foreach ($this->mergedInPeriod() as $pr) {
            $seconds = $this->seconds($pr->opened_at, $pr->merged_at);

            foreach ($buckets as $label => $upper) {
                if ($seconds < $upper) {
                    $counts[$label]++;

                    break;
                }
            }
        }

        return array_map(
            fn (string $label, int $count): array => ['label' => $label, 'count' => $count],
            array_keys($counts),
            array_values($counts),
        );
    }

    /**
     * Daily or weekly series across the period.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trend(): array
    {
        $starts = $this->period->buckets();
        $merged = array_fill(0, count($starts), []);
        $pickups = array_fill(0, count($starts), []);
        $opened = array_fill(0, count($starts), 0);

        foreach ($this->pullRequests as $pr) {
            if ($this->period->charts($mergedAt = $this->attributed($pr, $pr->merged_at))) {
                $merged[$this->period->bucketIndex($mergedAt)][] = $this->seconds($pr->opened_at, $pr->merged_at);
            }

            if ($this->period->charts($openedAt = $this->attributed($pr, $pr->opened_at))) {
                $opened[$this->period->bucketIndex($openedAt)]++;
            }

            if ($this->period->charts($respondedAt = $this->attributed($pr, $pr->first_response_at))) {
                $pickups[$this->period->bucketIndex($respondedAt)][] = $this->seconds($pr->readyForReviewAt(), $pr->first_response_at);
            }
        }

        $runs = $this->ci?->trend($this->period) ?? [];
        $deployments = $this->delivery?->trend($this->period) ?? [];
        $open = $this->openPerBucket();

        return array_map(function (int $index, CarbonImmutable $start) use ($merged, $opened, $pickups, $runs, $deployments, $open): array {
            $point = [
                'date' => $start->toDateString(),
                'deployments' => $deployments[$index] ?? 0,
                'merged' => count($merged[$index]),
                'opened' => $opened[$index],
                'open' => $open['ready'][$index],
                'openDrafts' => $open['draft'][$index],
                'cycleTime' => Stats::median($merged[$index]),
                'pickupTime' => Stats::median($pickups[$index]),
                'ciRuns' => $runs[$index]['runs'] ?? 0,
                'ciFailureRate' => Stats::ratio($runs[$index]['failed'] ?? 0, $runs[$index]['rated'] ?? 0),
            ];

            // Deployments and CI runs belong to repositories, not to the people on a team.
            return $this->repository === null ? array_diff_key($point, array_flip(['deployments', 'ciRuns', 'ciFailureRate'])) : $point;
        }, array_keys($starts), $starts);
    }

    /**
     * @return array{ready: array<int, int>, draft: array<int, int>}
     */
    private function openPerBucket(): array
    {
        return $this->openPerBucket ??= $this->openAtBucketEnds($this->period->buckets());
    }

    /**
     * How many pull requests were open at the end of each bucket, or now for the bucket still filling
     * up, split by whether they were ready for review or still drafts. Every pull request open at some
     * point in the period is loaded, since it is either still open or was merged or closed after the
     * period started.
     *
     * A pull request is a draft during its draft intervals, and ready the rest of the time it is open.
     * One not fetched since those were recorded is a draft from opening until it was first marked
     * ready, and ready from then on; if it is a draft again now, it only counts while it was a draft at
     * first, since when it went back is not known. A team report only counts it while its author was on
     * the team.
     *
     * Each pull request is open for a run of consecutive bucket ends, so it marks where that run starts
     * and stops, and a running total adds them up. That keeps all-time reports grouped per day fast.
     *
     * @param  list<CarbonImmutable>  $starts
     * @return array{ready: array<int, int>, draft: array<int, int>}
     */
    private function openAtBucketEnds(array $starts): array
    {
        $ends = array_map(fn (CarbonImmutable $start): int => $start->addDays($this->period->bucketDays())->min($this->period->to)->min($this->now)->getTimestamp(), $starts);
        $ready = array_fill(0, count($ends) + 1, 0);
        $draft = $ready;

        foreach ($this->pullRequests as $pr) {
            $readyAt = $pr->ready_at?->getTimestamp();
            $endedAt = ($pr->merged_at ?? $pr->closed_at)?->getTimestamp();

            foreach ($this->spans($pr) as $span) {
                if ($pr->draft_intervals !== null) {
                    self::markDraftIntervals($ready, $draft, $ends, $span, $pr->opened_at->getTimestamp(), $endedAt, $pr->draft_intervals);

                    continue;
                }

                $draftUntil = match (true) {
                    $readyAt === null => $endedAt,
                    $endedAt === null => $readyAt,
                    default => min($readyAt, $endedAt),
                };

                self::markOpen($draft, $ends, $span, $pr->opened_at->getTimestamp(), $draftUntil);

                if ($readyAt !== null && ! ($pr->is_draft && $pr->state === PullRequestState::Open)) {
                    self::markOpen($ready, $ends, $span, $readyAt, $endedAt);
                }
            }
        }

        return ['ready' => self::runningTotals($ready, count($ends)), 'draft' => self::runningTotals($draft, count($ends))];
    }

    /**
     * Mark a pull request as a draft during each of its draft intervals and as ready in between, while it
     * is open.
     *
     * @param  array<int, int>  $ready
     * @param  array<int, int>  $draft
     * @param  list<int>  $ends
     * @param  array{from: int|null, until: int|null}  $span  When it counts for the report.
     * @param  int|null  $endedAt  Null while it is still open.
     * @param  list<array{from: int, until: int|null}>  $intervals
     */
    private static function markDraftIntervals(array &$ready, array &$draft, array $ends, array $span, int $openedAt, ?int $endedAt, array $intervals): void
    {
        $readySince = $openedAt;

        foreach ($intervals as $interval) {
            $from = max($interval['from'], $openedAt);
            $until = match (true) {
                $interval['until'] === null => $endedAt,
                $endedAt === null => $interval['until'],
                default => min($interval['until'], $endedAt),
            };

            if ($readySince !== null) {
                self::markOpen($ready, $ends, $span, $readySince, $endedAt === null ? $from : min($from, $endedAt));
            }

            self::markOpen($draft, $ends, $span, $from, $until);
            $readySince = $until;
        }

        if ($readySince !== null) {
            self::markOpen($ready, $ends, $span, $readySince, $endedAt);
        }
    }

    /**
     * Mark a pull request open at every bucket end from the first at or after it opened, up to the first
     * at or after it closed, within the span it counts for the report.
     *
     * @param  array<int, int>  $changes
     * @param  list<int>  $ends
     * @param  array{from: int|null, until: int|null}  $span
     * @param  int|null  $until  Null while it is still open.
     */
    private static function markOpen(array &$changes, array $ends, array $span, int $from, ?int $until): void
    {
        $from = max($from, $span['from'] ?? $from);
        $until = $until === null || $span['until'] === null ? $until ?? $span['until'] : min($until, $span['until']);
        $first = self::firstAtOrAfter($ends, $from);
        $last = $until === null ? count($ends) : self::firstAtOrAfter($ends, $until);

        if ($last > $first) {
            $changes[$first]++;
            $changes[$last]--;
        }
    }

    /**
     * @param  array<int, int>  $changes
     * @return array<int, int>
     */
    private static function runningTotals(array $changes, int $count): array
    {
        $totals = [];
        $running = 0;

        for ($index = 0; $index < $count; $index++) {
            $totals[$index] = $running += $changes[$index];
        }

        return $totals;
    }

    /**
     * The index of the first timestamp at or after the given one, or the count when there is none.
     *
     * @param  list<int>  $timestamps  Sorted ascending.
     */
    private static function firstAtOrAfter(array $timestamps, int $timestamp): int
    {
        $low = 0;
        $high = count($timestamps);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($timestamps[$middle] < $timestamp) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * @return list<array{label: SizeBucket, max: int|null, count: int, cycleTime: float|null}>
     */
    private function sizeDistribution(): array
    {
        $grouped = $this->mergedInPeriod()->groupBy(fn (PullRequest $pr): string => SizeBucket::for($pr->size())->value);

        return array_map(fn (SizeBucket $bucket): array => [
            'label' => $bucket,
            'max' => $bucket->maxLines(),
            'count' => $grouped->get($bucket->value, collect())->count(),
            'cycleTime' => Stats::median($grouped->get($bucket->value, collect())->map(fn (PullRequest $pr): int => $this->seconds($pr->opened_at, $pr->merged_at))),
        ], SizeBucket::cases());
    }

    /**
     * Every open pull request with what it is currently waiting on.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workInProgress(): array
    {
        return $this->timelines($this->openNow())
            ->map(function (PullRequest $pr): array {
                $flow = $pr->is_draft ? null : $this->flow($pr);

                return [
                    'repository' => $pr->repository->fullName(),
                    'number' => $pr->number,
                    'title' => $pr->title,
                    'url' => $pr->url,
                    'author' => $pr->author,
                    'authorAvatarUrl' => $pr->author_avatar_url,
                    'status' => $flow?->status() ?? OpenPullRequestStatus::Draft,
                    'checks' => $pr->checks_state,
                    'size' => $pr->size(),
                    'sizeLabel' => SizeBucket::for($pr->size()),
                    'openedAt' => $pr->opened_at->toIso8601String(),
                    'ageSeconds' => $this->seconds($pr->opened_at, $this->now),
                    'waitingSeconds' => $flow ? $this->seconds($flow->stateSince, $this->now) : null,
                    'rounds' => $flow === null ? 0 : $flow->rounds,
                    'lastActivityAt' => $this->lastHumanActivity($pr)->toIso8601String(),
                    'isStale' => $this->isStale($pr),
                    'reviewers' => collect($this->timeline($pr)->reviews)->pluck('reviewer')->reject(fn (string $reviewer): bool => $reviewer === $pr->author)->unique()->values()->all(),
                ];
            })
            // Actionable work first, longest wait first; drafts last.
            ->sortBy([
                fn (array $a, array $b): int => ($a['status'] === OpenPullRequestStatus::Draft) <=> ($b['status'] === OpenPullRequestStatus::Draft),
                fn (array $a, array $b): int => ($b['waitingSeconds'] ?? 0) <=> ($a['waitingSeconds'] ?? 0),
                fn (array $a, array $b): int => $a['openedAt'] <=> $b['openedAt'],
            ])
            ->values()
            ->all();
    }

    /**
     * How many pull requests the team opened or merged in the period, per repository by owner/name.
     *
     * @return array<string, int>
     */
    private function pullRequestsPerRepository(): array
    {
        return $this->pullRequests
            ->filter(fn (PullRequest $pr): bool => $this->period->contains($this->attributed($pr, $pr->opened_at)) || $this->period->contains($this->attributed($pr, $pr->merged_at)))
            ->countBy(fn (PullRequest $pr): string => $pr->repository->fullName())
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflows(): array
    {
        return $this->ci()->workflows($this->period);
    }

    /**
     * @return array{state: BranchStatus, failingWorkflows: array<int, string>, brokenSince: string|null, lastRunAt: string|null}
     */
    private function defaultBranchStatus(): array
    {
        return $this->ci()->defaultBranchStatus();
    }

    /**
     * @return array<string, mixed>
     */
    private function deliverySummary(): array
    {
        return [
            'source' => $this->repository()->sync?->deploy_source,
            'recent' => $this->delivery()->recent(6),
            'pending' => $this->delivery()->pending($this->now),
        ];
    }

    /**
     * Reverts and hotfixes merged in the period.
     *
     * @return array<int, array<string, mixed>>
     */
    private function remediations(): array
    {
        return $this->mergedInPeriod()
            ->filter->isRemediation()
            ->sortByDesc(fn (PullRequest $pr): int => $pr->merged_at?->getTimestamp() ?? 0)
            ->take(8)
            ->map(fn (PullRequest $pr): array => [
                'repository' => $pr->repository->fullName(),
                'number' => $pr->number,
                'title' => $pr->title,
                'url' => $pr->url,
                'author' => $pr->author,
                'authorAvatarUrl' => $pr->author_avatar_url,
                'mergedAt' => $pr->merged_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Dependency and other bot pull requests, which are excluded from the main metrics but can quietly pile up.
     *
     * @return array{open: int, oldestSeconds: int|null, medianAgeSeconds: float|null, merged: int, cycleTime: float|null, oldest: array<int, array<string, mixed>>}
     */
    private function botBacklog(): array
    {
        $bots = $this->repository()->pullRequests()
            ->where('author_is_bot', true)
            ->where(fn (Builder $query) => $query->where('state', PullRequestState::Open)->orWhere('merged_at', '>=', $this->period->from))
            ->get();

        $open = $bots->where('state', PullRequestState::Open)->sortBy(fn (PullRequest $pr): int => $pr->opened_at->getTimestamp());
        $merged = $bots->filter(fn (PullRequest $pr): bool => $this->period->contains($pr->merged_at));
        $ages = $open->map(fn (PullRequest $pr): int => $this->seconds($pr->opened_at, $this->now));

        return [
            'open' => $open->count(),
            'oldestSeconds' => $ages->max(),
            'medianAgeSeconds' => Stats::median($ages),
            'merged' => $merged->count(),
            'cycleTime' => Stats::median($merged->map(fn (PullRequest $pr): int => $this->seconds($pr->opened_at, $pr->merged_at))),
            'oldest' => $open->take(5)->map(fn (PullRequest $pr): array => [
                'number' => $pr->number,
                'title' => $pr->title,
                'url' => $pr->url,
                'author' => $pr->author,
                'ageSeconds' => $this->seconds($pr->opened_at, $this->now),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slowestMerged(): array
    {
        return $this->mergedInPeriod()
            ->sortByDesc(fn (PullRequest $pr): int => $this->seconds($pr->opened_at, $pr->merged_at))
            ->take(8)
            ->map(fn (PullRequest $pr): array => [
                'repository' => $pr->repository->fullName(),
                'number' => $pr->number,
                'title' => $pr->title,
                'url' => $pr->url,
                'author' => $pr->author,
                'authorAvatarUrl' => $pr->author_avatar_url,
                'cycleTime' => $this->seconds($pr->opened_at, $pr->merged_at),
                'pickupTime' => $pr->first_response_at ? $this->seconds($pr->readyForReviewAt(), $pr->first_response_at) : null,
                'size' => $pr->size(),
                'sizeLabel' => SizeBucket::for($pr->size()),
                'mergedAt' => $pr->merged_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PullRequest>
     */
    private function mergedInPeriod(): Collection
    {
        return $this->pullRequests->filter(fn (PullRequest $pr): bool => $this->period->contains($this->attributed($pr, $pr->merged_at)));
    }

    private function repository(): Repository
    {
        return $this->repository ?? throw new LogicException('Only repository reports have this.');
    }

    private function ci(): CiMetrics
    {
        return $this->ci ?? throw new LogicException('Only repository reports have CI runs.');
    }

    private function delivery(): DeliveryMetrics
    {
        return $this->delivery ?? throw new LogicException('Only repository reports have deployments.');
    }

    /**
     * No human has touched the pull request for a while. Bot activity (preview deploys,
     * dependency bumps) keeps updatedAt fresh, so it doesn't count.
     */
    private function isStale(PullRequest $pr): bool
    {
        return $this->lastHumanActivity($pr)->lt($this->now->subDays(config()->integer('houston.stale_pull_request_days')));
    }

    private function lastHumanActivity(PullRequest $pr): CarbonImmutable
    {
        $moments = [$pr->opened_at->getTimestamp(), $pr->ready_at?->getTimestamp() ?? 0];

        $timeline = $this->timeline($pr);

        foreach ($timeline->reviews as $review) {
            if (! $review['isBot']) {
                $moments[] = $review['at'];
            }
        }

        array_push($moments, ...$timeline->activityTimes);

        return Timeline::moment(max($moments));
    }

    /**
     * Working time between two moments. Used for everything that measures people rather than machines.
     */
    private function seconds(?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return $this->durations[$from->getTimestamp().':'.$to->getTimestamp()] ??= $this->time->between($from, $to);
    }
}
