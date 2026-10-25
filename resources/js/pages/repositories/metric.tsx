import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeftIcon } from '@/components/icons';
import { TimeSeriesChart } from '@/components/charts/time-series-chart';
import { MetricCard } from '@/components/metric-card';
import { MetricIcon } from '@/components/metric-icons';
import { ReportOptions } from '@/components/repository/toolbar';
import { Grid, Cell } from '@/components/report/grid';
import { Legend, SkeletonCell } from '@/components/report/widgets';
import { SyncStatus } from '@/components/sync-status';
import { Avatar } from '@/components/ui/avatar';
import { Panel } from '@/components/ui/panel';
import { Select } from '@/components/ui/select';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import { useLookbackConfirmation } from '@/hooks/use-lookback-confirmation';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useReloadOnChange } from '@/hooks/use-reload-on-change';
import AppLayout from '@/layouts/app-layout';
import { METRIC_GROUPS, bucketThresholds } from '@/lib/benchmarks';
import type { MetricInfo } from '@/lib/benchmarks';
import { formatDate, formatMetric, metricDelta } from '@/lib/format';
import { historyKind } from '@/lib/metric-history';
import {
    bucketLabel,
    reportQuery,
    ReportPeriod,
    type ReportFilters,
} from '@/lib/periods';
import { TimelineEvents } from '@/lib/timeline-events';
import { cn } from '@/lib/utils';
import { metric as metricRoute, show } from '@/routes/repositories';
import type {
    FocusedTimelineEvent,
    Metric,
    MetricKey,
    MetricStatistic,
    RepositoryInfo,
    TimelineEvent,
    VelocityReport,
} from '@/types';

type Props = {
    repository: RepositoryInfo;
    metric: MetricKey;
    filters: ReportFilters;
    events: TimelineEvent[];
    event: FocusedTimelineEvent | null;
    report: {
        period: VelocityReport['period'];
        kpi: Metric;
        /** Where each trend bucket starts. */
        dates: string[];
        /** The metric per bucket, or null for one only known right now. */
        values: (number | null)[] | null;
        /** For a metric that sums up one value per item, the median and average of them per bucket. */
        median?: (number | null)[];
        average?: (number | null)[];
        metrics: MetricKey[];
    };
};

type Statistics = Record<MetricStatistic, (number | null)[]>;

const STATISTIC_LABELS: Record<MetricStatistic, string> = {
    median: 'Median',
    average: 'Average',
};

const METRICS = Object.fromEntries(
    METRIC_GROUPS.flatMap((group) => Object.entries(group.metrics)),
) as Record<MetricKey, MetricInfo>;

const BUCKET_NAMES = { day: 'day', week: 'week', month: '30 days' } as const;

export default function RepositoryMetric({
    repository,
    metric,
    filters,
    events,
    event,
    report,
}: Props) {
    const info = METRICS[metric];
    const label = info?.label ?? metric;

    usePollWhile(repository.sync.inProgress, ['repository']);
    useReloadOnChange(
        `${repository.sync.hasData}:${repository.sync.finishedAt}`,
        ['report'],
    );

    const url = (key: MetricKey, next: ReportFilters) =>
        metricRoute.url(
            { owner: repository.owner, name: repository.name, metric: key },
            { query: reportQuery(next) },
        );

    const visit = (key: MetricKey, changes: Partial<ReportFilters>) =>
        router.get(
            url(key, {
                ...filters,
                // Picking another period leaves the event behind.
                ...('days' in changes ? { event: null } : {}),
                ...changes,
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const { choosePeriod, dialog } = useLookbackConfirmation(
        [repository],
        filters,
    );

    return (
        <AppLayout>
            <Head title={`${label} · ${repository.fullName}`} />

            <Link
                href={show.url(
                    { owner: repository.owner, name: repository.name },
                    { query: reportQuery(filters) },
                )}
                className="inline-flex items-center gap-1 text-xs text-ink-muted hover:text-ink"
            >
                <ChevronLeftIcon width={13} height={13} />
                <Avatar
                    src={repository.avatarUrl}
                    login={repository.owner}
                    size={14}
                    className="rounded"
                />
                {repository.fullName}
            </Link>

            <div className="mt-3 flex min-w-0 flex-wrap items-end justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface text-ink-secondary">
                        <MetricIcon metric={metric} width={20} height={20} />
                    </span>
                    <div className="min-w-0">
                        <h1 className="truncate text-2xl font-semibold tracking-tight">
                            {label}
                        </h1>
                        {info?.hint && (
                            <p className="mt-0.5 text-sm text-ink-secondary">
                                {info.hint}
                            </p>
                        )}
                    </div>
                </div>
                <Select
                    label="Metric"
                    search
                    value={metric}
                    onChange={(key) => visit(key, {})}
                    className="max-w-64"
                    options={report.metrics.map((key) => ({
                        value: key,
                        label: METRICS[key]?.label ?? key,
                        icon: (
                            <MetricIcon
                                metric={key}
                                width={14}
                                height={14}
                                className="text-ink-muted"
                            />
                        ),
                    }))}
                />
            </div>

            <StickyToolbar className="mt-3 justify-end">
                <SyncStatus repository={repository} />
                <ReportOptions
                    filters={filters}
                    granularity={report.period.granularity}
                    days={report.period.days}
                    onChange={(changes) =>
                        choosePeriod({ ...filters, ...changes }, () =>
                            visit(metric, changes),
                        )
                    }
                />
            </StickyToolbar>
            {dialog}

            {repository.sync.hasData ? (
                <ReportPeriod value={filters}>
                    <TimelineEvents
                        value={{
                            events,
                            focused: event,
                            clearHref: url(metric, { ...filters, event: null }),
                            bucketDays: report.period.bucketDays,
                            href: (timelineEvent) =>
                                metricRoute.url(
                                    {
                                        owner: repository.owner,
                                        name: repository.name,
                                        metric,
                                    },
                                    {
                                        query: {
                                            event: timelineEvent.id,
                                            complete: filters.complete ? 1 : 0,
                                            bots: filters.bots ? 1 : undefined,
                                        },
                                    },
                                ),
                        }}
                    >
                        <MetricReport
                            metricKey={metric}
                            label={label}
                            hint={info?.hint}
                            report={report}
                        />
                    </TimelineEvents>
                </ReportPeriod>
            ) : (
                <div className="mt-8">
                    <Grid>
                        <SkeletonCell span="1/3" />
                        <SkeletonCell span="2/3" />
                        <SkeletonCell span="full" className="h-80" />
                    </Grid>
                </div>
            )}
        </AppLayout>
    );
}

function MetricReport({
    metricKey,
    label,
    hint,
    report,
}: {
    metricKey: MetricKey;
    label: string;
    hint?: string;
    report: Props['report'];
}) {
    const { kpi, dates, values, period } = report;
    const known = (values ?? []).filter(
        (value): value is number => value !== null,
    );
    const bucket = BUCKET_NAMES[period.granularity];
    const statistics: Statistics | null =
        report.median && report.average
            ? { median: report.median, average: report.average }
            : null;
    // The statistic the metric is reported as comes first and keeps the main color.
    const shown = kpi.statistic ?? 'median';
    const order: MetricStatistic[] =
        shown === 'median' ? ['median', 'average'] : ['average', 'median'];
    const series = statistics
        ? order.map((statistic, index) => ({
              key: statistic,
              label: STATISTIC_LABELS[statistic],
              color: index === 0 ? 'var(--series-1)' : 'var(--series-2)',
              values: statistics[statistic],
          }))
        : [
              {
                  key: metricKey,
                  label,
                  color: 'var(--series-1)',
                  values: values ?? [],
              },
          ];

    return (
        <div className="mt-6 flex flex-col gap-4">
            <Grid>
                <Cell span="1/3">
                    <MetricCard
                        label={label}
                        hint={hint}
                        metric={kpi}
                        icon={metricKey}
                        size="lg"
                    />
                </Cell>
                <Cell span="2/3">
                    <Panel title="How it's calculated" className="h-full">
                        <p className="text-sm leading-relaxed text-ink-secondary">
                            {kpi.description}
                        </p>
                        {known.length > 0 && (
                            <dl className="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 sm:grid-cols-4">
                                {bucketStats(values!, dates).map((stat) => (
                                    <div key={stat.label} className="min-w-0">
                                        <dt className="truncate text-[11px] text-ink-muted">
                                            {stat.label}
                                        </dt>
                                        <dd className="tabular text-sm font-semibold text-ink">
                                            {formatMetric(stat.value, kpi.unit)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </Panel>
                </Cell>
            </Grid>

            {values === null ? (
                <Panel>
                    <p className="text-sm text-ink-muted">
                        {label} is only known right now, so there is no history
                        to chart.
                    </p>
                </Panel>
            ) : (
                <>
                    <Panel title="Over time" description={`Per ${bucket}`}>
                        {known.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                Nothing to chart in this period.
                            </p>
                        ) : (
                            <>
                                {statistics && (
                                    <Legend
                                        items={series.map((item, index) => ({
                                            label:
                                                index === 0
                                                    ? `${item.label} · shown`
                                                    : item.label,
                                            color: item.color,
                                        }))}
                                    />
                                )}
                                <TimeSeriesChart
                                    kind={historyKind(metricKey, kpi)}
                                    labels={dates.map((date) =>
                                        formatDate(date),
                                    )}
                                    tooltipLabels={dates.map((date) =>
                                        bucketLabel(date, period),
                                    )}
                                    dates={dates}
                                    durationAxis={kpi.unit === 'duration'}
                                    benchmark={bucketThresholds(
                                        kpi,
                                        period.bucketDays,
                                    )}
                                    height={320}
                                    series={series}
                                    format={(value) =>
                                        formatMetric(value, kpi.unit, true)
                                    }
                                />
                            </>
                        )}
                    </Panel>

                    <Panel
                        title={`Each ${bucket}`}
                        description="Most recent first"
                        bodyClassName="px-5 pt-2 pb-3"
                    >
                        <BucketTable
                            kpi={kpi}
                            values={values}
                            statistics={
                                statistics && { order, values: statistics }
                            }
                            dates={dates}
                            period={period}
                        />
                    </Panel>
                </>
            )}
        </div>
    );
}

/** The lowest, highest, middle and latest bucket, for reading the chart without hovering it. */
function bucketStats(values: (number | null)[], dates: string[]) {
    const known = values
        .filter((value): value is number => value !== null)
        .sort((a, b) => a - b);
    const latest = values.findLastIndex((value) => value !== null);
    const middle = known.length / 2;

    return [
        { label: 'Lowest', value: known[0] },
        { label: 'Highest', value: known[known.length - 1] },
        {
            label: 'Median',
            value:
                known.length % 2
                    ? known[Math.floor(middle)]
                    : (known[middle - 1] + known[middle]) / 2,
        },
        {
            label: `Latest · ${formatDate(dates[latest])}`,
            value: values[latest],
        },
    ];
}

function BucketTable({
    kpi,
    values,
    statistics,
    dates,
    period,
}: {
    kpi: Metric;
    values: (number | null)[];
    /** Both statistics per bucket, the one the metric is reported as first. */
    statistics: { order: MetricStatistic[]; values: Statistics } | null;
    dates: string[];
    period: Props['report']['period'];
}) {
    const rows = dates
        .map((date, index) => ({
            date,
            index,
            value: values[index],
            delta:
                index > 0
                    ? metricDelta({
                          ...kpi,
                          value: values[index],
                          previous: values[index - 1],
                      })
                    : null,
        }))
        .reverse();

    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="text-left text-[11px] text-ink-muted">
                    <th className="py-2 font-normal">Period</th>
                    <th className="py-2 text-right font-normal">Change</th>
                    {statistics ? (
                        statistics.order.map((statistic) => (
                            <th
                                key={statistic}
                                className="w-28 py-2 text-right font-normal"
                            >
                                {STATISTIC_LABELS[statistic]}
                            </th>
                        ))
                    ) : (
                        <th className="w-28 py-2 text-right font-normal">
                            Value
                        </th>
                    )}
                </tr>
            </thead>
            <tbody className="divide-y divide-line">
                {rows.map((row) => (
                    <tr key={row.date}>
                        <td className="py-2 text-ink-secondary">
                            {bucketLabel(row.date, period)}
                        </td>
                        <td
                            className={cn(
                                'tabular py-2 text-right text-xs',
                                row.delta?.tone === 'good' && 'text-good',
                                row.delta?.tone === 'bad' && 'text-bad',
                                (!row.delta || row.delta.tone === 'neutral') &&
                                    'text-ink-muted',
                            )}
                        >
                            {row.delta
                                ? row.delta.direction === 'flat'
                                    ? 'no change'
                                    : `${row.delta.direction === 'up' ? '↑' : '↓'} ${row.delta.label}`
                                : ''}
                        </td>
                        {statistics ? (
                            statistics.order.map((statistic, index) => (
                                <td
                                    key={statistic}
                                    className={cn(
                                        'tabular py-2 text-right',
                                        index === 0
                                            ? 'font-semibold text-ink'
                                            : 'text-ink-secondary',
                                    )}
                                >
                                    {formatMetric(
                                        statistics.values[statistic][row.index],
                                        kpi.unit,
                                    )}
                                </td>
                            ))
                        ) : (
                            <td className="tabular py-2 text-right font-semibold text-ink">
                                {formatMetric(row.value, kpi.unit)}
                            </td>
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
