<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PresentsRepositories;
use App\Http\Controllers\Concerns\PresentsTimelineEvents;
use App\Jobs\SyncRepository;
use App\Metrics\Ci\CiJob;
use App\Metrics\Ci\CiMetric;
use App\Metrics\Ci\FlakyJobMetric;
use App\Metrics\Distribution;
use App\Metrics\Metric;
use App\Metrics\Metrics;
use App\Metrics\Period;
use App\Models\Repository;
use App\Models\TimelineEvent;
use App\Reports\ReportCache;
use App\Settings\ReportDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One metric of a repository on a page of its own, charted across the period.
 */
class RepositoryMetricController extends Controller
{
    use PresentsRepositories;
    use PresentsTimelineEvents;

    public function __construct(private ReportCache $reports) {}

    public function __invoke(Request $request, string $owner, string $name, string $metric): Response
    {
        $repository = Repository::query()->with('sync')->where('owner', $owner)->where('name', $name)->firstOrFail();
        abort_unless(array_key_exists($metric, Metrics::all()), 404);

        $event = $this->focusedEvent($request);
        $period = Period::fromRequest($request, lookbackDays: $repository->lookbackDays(), since: $event?->happened_on)->withoutCurrentBucket($request->has('complete') ? $request->boolean('complete') : ReportDefaults::current()->withoutCurrentBucket)
            // Comparing with the time before an event charts both, side by side.
            ->chartingPrevious($event !== null);
        $includeBots = $request->boolean('bots');
        // Only the flaky-job metrics can be narrowed to one job.
        $job = Metrics::all()[$metric] instanceof FlakyJobMetric ? CiJob::fromRequest($request) : null;

        if ($repository->isStale()) {
            SyncRepository::trigger($repository, 'stale when viewed');
            $repository->load('sync');
        }

        return Inertia::render('repositories/metric', [
            'repository' => $this->repositoryPayload($repository),
            'metric' => $metric,
            'filters' => [...$period->filters(), 'granularity' => $period->granularity, 'complete' => $period->withoutCurrentBucket, 'bots' => $includeBots, 'event' => $event?->id],
            'events' => TimelineEvent::payloads(),
            'event' => $this->focusedEventPayload($event, $repository->lookbackStart()),
            'job' => $job === null ? null : [
                'workflowId' => $job->workflowId,
                'name' => $job->name,
                'workflow' => $repository->workflowRuns()->where('workflow_id', $job->workflowId)->latest('created_at')->value('name'),
            ],
            'report' => fn (): array => $job === null
                ? $this->report($repository, $period, $includeBots, $metric)
                : $this->jobReport($repository, $period, $includeBots, $metric, $job),
        ]);
    }

    /**
     * A flaky-job metric narrowed to one job, which can switch between the other flaky-job metrics of
     * the same job.
     *
     * @return array<string, mixed>
     */
    private function jobReport(Repository $repository, Period $period, bool $includeBots, string $metric, CiJob $job): array
    {
        $report = $this->reports->full($repository, $period, $includeBots);

        return [
            ...Arr::only($report, ['period', 'workingTime']),
            ...$this->reports->jobMetric($repository, $period, $metric, $job),
            'dates' => array_column($report['trend'], 'date'),
            'metrics' => array_keys(array_filter(Metrics::all(), fn (Metric $definition): bool => $definition instanceof FlakyJobMetric)),
        ];
    }

    /**
     * The metric's headline numbers and its value per trend bucket, or null values for a metric only
     * known right now. It shares its cache with the repository page. A CI metric, which the full
     * report doesn't break down per bucket, and a metric that sums up one value per item, whose median
     * and average per bucket it doesn't carry, get a cached series of their own.
     *
     * @return array<string, mixed>
     */
    private function report(Repository $repository, Period $period, bool $includeBots, string $metric): array
    {
        $report = $this->reports->full($repository, $period, $includeBots);
        $definition = Metrics::all()[$metric];
        /** @var array<string, mixed> $series */
        $series = $report['series'];

        return [
            ...Arr::only($report, ['period', 'workingTime']),
            'kpi' => $report['kpis'][$metric],
            'dates' => array_column($report['trend'], 'date'),
            ...($definition instanceof CiMetric || $definition instanceof Distribution
                ? $this->reports->metricSeries($repository, $period, $metric, $includeBots)
                : ['values' => $series[$metric] ?? null]),
            // Every metric the repository reports, to switch between.
            'metrics' => array_keys($report['kpis']),
        ];
    }
}
