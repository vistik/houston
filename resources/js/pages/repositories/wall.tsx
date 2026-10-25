import { Head, usePoll } from '@inertiajs/react';
import { niceDurationScale, niceScale } from '@/components/charts/scale';
import {
    offChartTier,
    TIER_LINES,
} from '@/components/charts/time-series-chart';
import { BenchmarkBadge, MetricLabel } from '@/components/metric-card';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    BranchPill,
    WaitingList,
    WALL_BADGE,
    WallDelta,
    WallFooter,
    WallStageBar,
} from '@/components/wall';
import { useElementSize } from '@/hooks/use-element-width';
import { useWallRotation } from '@/hooks/use-wall-rotation';
import { bucketThresholds, TIER_LABELS } from '@/lib/benchmarks';
import { formatDate, formatMetric, formatNumber } from '@/lib/format';
import { ReportPeriod } from '@/lib/periods';
import { viewMetrics } from '@/lib/repository-views';
import type { RepositoryView, WallSlide } from '@/lib/repository-views';
import type {
    CiFailureRateScope,
    CycleStage,
    DefaultBranchStatus,
    Delivery,
    Metric,
    MetricKey,
    MetricUnit,
    OpenPullRequest,
    Thresholds,
    TrendPoint,
    VelocityReport,
} from '@/types';

type Repository = {
    id: number;
    owner: string;
    name: string;
    fullName: string;
    avatarUrl: string | null;
    defaultBranch: string | null;
    view: RepositoryView;
    sync: { status: string; hasData: boolean; finishedAt: string | null };
};

type Report = {
    period: VelocityReport['period'];
    kpis: Record<MetricKey, Metric>;
    trend: TrendPoint[];
    series: Partial<
        Record<'reviewTime' | 'pullRequestSize', (number | null)[]>
    >;
    cycleBreakdown: CycleStage[];
    ciFailureRateScope: CiFailureRateScope;
    defaultBranchStatus: DefaultBranchStatus;
    waitingForReview: OpenPullRequest[];
    delivery: Delivery;
    workingTime: string;
};

type Props = {
    days: number;
    rotateSeconds: number;
    repository: Repository;
    report: Report;
};

type ChartSeries = {
    label: string;
    color: string;
    values: (number | null)[];
};

type Slide =
    | {
          kind: 'metric';
          key: WallSlide;
          title: string;
          hint: string;
          metric: Metric;
          chart: { type: 'bar' | 'line'; series: ChartSeries[] };
          details: { label: string; metric: Metric }[];
      }
    | { kind: 'stages'; key: 'stages'; title: string; hint: string }
    | { kind: 'waiting'; key: 'waiting'; title: string; hint: string };

const STALE_AFTER_MS = 60 * 60 * 1000;

export default function RepositoryWall({
    days,
    rotateSeconds,
    repository,
    report,
}: Props) {
    usePoll(60_000, { only: ['repository', 'report'] });

    const slides = repository.sync.hasData
        ? buildSlides(
              report,
              repository.defaultBranch ?? 'main',
              repository.view,
          )
        : [];
    const { current, index, show, paused, progress, now } = useWallRotation(
        slides.map((slide) => slide.key),
        rotateSeconds,
    );
    const slide = slides.find((entry) => entry.key === current);

    const stale =
        repository.sync.status === 'failed' ||
        !repository.sync.finishedAt ||
        now - new Date(repository.sync.finishedAt).getTime() > STALE_AFTER_MS;

    return (
        <div className="flex min-h-dvh flex-col bg-page px-4 pt-4 text-ink wall:h-screen wall:overflow-hidden wall:px-[3vw] wall:pt-[3vh]">
            <Head title={`${repository.name} · Wall`} />

            <ReportPeriod value={{ days, from: null, to: null, all: false }}>
                <div className="flex flex-1 flex-col pb-4 wall:min-h-0 wall:pb-0">
                    <Header
                        repository={repository}
                        days={days}
                        status={
                            repository.sync.hasData
                                ? report.defaultBranchStatus
                                : null
                        }
                    />

                    {!slide ? (
                        <div className="flex flex-1 items-center justify-center text-lg text-ink-muted wall:text-[3vh]">
                            Fetching history from GitHub…
                        </div>
                    ) : (
                        <div
                            key={slide.key}
                            className="mt-6 flex flex-1 flex-col wall:mt-[3vh] wall:min-h-0"
                        >
                            {slide.kind === 'metric' ? (
                                <MetricSlide
                                    slide={slide}
                                    granularity={report.period.granularity}
                                    bucketDays={report.period.bucketDays}
                                    labels={report.trend.map((point) =>
                                        formatDate(point.date),
                                    )}
                                />
                            ) : (
                                <PanelSlide
                                    title={slide.title}
                                    hint={slide.hint}
                                >
                                    {slide.kind === 'stages' ? (
                                        <WallStageBar
                                            stages={report.cycleBreakdown}
                                        />
                                    ) : (
                                        <WaitingList
                                            pullRequests={
                                                report.waitingForReview
                                            }
                                        />
                                    )}
                                </PanelSlide>
                            )}
                        </div>
                    )}
                </div>
            </ReportPeriod>

            <WallFooter
                labelled
                slides={slides.map((entry) => ({
                    key: entry.key,
                    label: entry.title,
                }))}
                index={index}
                progress={progress}
                paused={paused}
                now={now}
                onSelect={(position) => show(slides[position].key)}
                warning={stale ? 'Data may be stale' : null}
            />
        </div>
    );
}

/**
 * The metrics worth a screen of their own, in the order they rotate. Slides without data are left out.
 */
function buildSlides(
    report: Report,
    branch: string,
    view: RepositoryView,
): Slide[] {
    const { kpis, trend, series, delivery, ciFailureRateScope } = report;
    const onDefaultBranch = ciFailureRateScope === 'default_branch';
    const slides: Slide[] = [
        {
            kind: 'metric',
            key: 'cycleTime',
            title: 'Time to merge',
            hint: 'Median, opened → merged',
            metric: kpis.cycleTime,
            chart: {
                type: 'line',
                series: [
                    {
                        label: 'Time to merge',
                        color: 'var(--series-1)',
                        values: trend.map((point) => point.cycleTime),
                    },
                ],
            },
            details: [
                { label: 'Slowest 10% over', metric: kpis.cycleTimeP90 },
                { label: 'From first commit', metric: kpis.leadTime },
                { label: 'PR size', metric: kpis.pullRequestSize },
            ],
        },
        {
            kind: 'metric',
            key: 'pickupTime',
            title: 'Pickup time',
            hint: 'Median wait for a first response',
            metric: kpis.pickupTime,
            chart: {
                type: 'line',
                series: [
                    {
                        label: 'Pickup time',
                        color: 'var(--series-2)',
                        values: trend.map((point) => point.pickupTime),
                    },
                ],
            },
            details: [
                { label: 'Time to approval', metric: kpis.timeToApproval },
                {
                    label: 'Waiting on reviewers',
                    metric: kpis.waitingOnReviewer,
                },
                {
                    label: 'Merged unreviewed',
                    metric: kpis.unreviewedMergeRate,
                },
            ],
        },
        {
            kind: 'metric',
            key: 'reviewTime',
            title: 'Review time',
            hint: 'Median, first response → merged',
            metric: kpis.reviewTime,
            chart: {
                type: 'line',
                series: [
                    {
                        label: 'Review time',
                        color: 'var(--series-5)',
                        values: series.reviewTime ?? [],
                    },
                ],
            },
            details: [
                { label: 'Review rounds', metric: kpis.reviewRounds },
                { label: 'Approval → merge', metric: kpis.mergeDelay },
                {
                    label: 'Changes requested',
                    metric: kpis.changesRequestedRate,
                },
            ],
        },
        {
            kind: 'metric',
            key: 'throughput',
            title: 'Merged PRs',
            hint: `${formatNumber(kpis.opened.value ?? 0)} opened`,
            metric: kpis.throughput,
            chart: {
                type: 'bar',
                series: [
                    {
                        label: 'Merged',
                        color: 'var(--series-1)',
                        values: trend.map((point) => point.merged),
                    },
                    {
                        label: 'Opened',
                        color: 'var(--series-2)',
                        values: trend.map((point) => point.opened),
                    },
                ],
            },
            details: [
                { label: 'Merge frequency', metric: kpis.mergeFrequency },
                { label: 'Merge rate', metric: kpis.mergeRate },
                { label: 'Active developers', metric: kpis.activeDevelopers },
            ],
        },
    ];

    if ((kpis.ciRuns.value ?? 0) > 0) {
        slides.push({
            kind: 'metric',
            key: 'ciFailureRate',
            title: 'CI failure rate',
            hint: onDefaultBranch
                ? `Failed / finished runs on ${branch}`
                : 'Failed / finished runs, all branches',
            metric: kpis.ciFailureRate,
            chart: {
                type: 'line',
                series: [
                    {
                        label: 'Failure rate',
                        color: 'var(--series-3)',
                        values: trend.map((point) => point.ciFailureRate),
                    },
                ],
            },
            details: [
                onDefaultBranch
                    ? {
                          label: 'PR builds',
                          metric: kpis.ciPullRequestFailureRate,
                      }
                    : {
                          label: `On ${branch}`,
                          metric: kpis.ciDefaultBranchFailureRate,
                      },
                { label: 'CI duration', metric: kpis.ciDuration },
                { label: 'Flaky', metric: kpis.ciFlakyRate },
            ],
        });
    }

    if (delivery.source !== null) {
        const releases = delivery.source === 'releases';

        slides.push({
            kind: 'metric',
            key: 'deployments',
            title: releases ? 'Releases' : 'Deploys',
            hint:
                delivery.pending.count > 0
                    ? `${delivery.pending.count} merged, not shipped yet`
                    : 'Everything merged has shipped',
            metric: kpis.deployments,
            chart: {
                type: 'bar',
                series: [
                    {
                        label: releases ? 'Releases' : 'Deploys',
                        color: 'var(--series-4)',
                        values: trend.map((point) => point.deployments),
                    },
                ],
            },
            details: [
                {
                    label: 'Lead time to prod',
                    metric: kpis.leadTimeToProduction,
                },
                { label: 'Needed a fix', metric: kpis.changeFailureRate },
                { label: 'Time to restore', metric: kpis.timeToRestore },
            ],
        });
    }

    slides.push(
        {
            kind: 'stages',
            key: 'stages',
            title: 'Where the time goes',
            hint: 'Average per merged PR, first commit → merge',
        },
        {
            kind: 'waiting',
            key: 'waiting',
            title: 'Waiting for review',
            hint:
                (kpis.stalePullRequests.value ?? 0) > 0
                    ? `${kpis.stalePullRequests.value} stale · ${kpis.openPullRequests.value} open`
                    : `${kpis.openPullRequests.value} open`,
        },
    );

    // The view picks which of the slides show, and in which order.
    return viewMetrics(view).wallSlides.flatMap((key) =>
        slides.filter((slide) => slide.key === key),
    );
}

function Header({
    repository,
    days,
    status,
}: {
    repository: Repository;
    days: number;
    status: DefaultBranchStatus | null;
}) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between wall:gap-[2vw]">
            <div className="flex min-w-0 items-center gap-3 wall:gap-[1.2vh]">
                <Avatar
                    src={repository.avatarUrl}
                    login={repository.owner}
                    size={40}
                    className="size-9 rounded-lg wall:size-[4.4vh] wall:rounded-[0.9vh]"
                />
                <div className="min-w-0 truncate text-xl font-semibold tracking-tight wall:text-[3vh]">
                    <span className="text-ink-muted">{repository.owner}/</span>
                    {repository.name}
                    <span className="ml-3 text-base font-normal text-ink-muted wall:ml-[1.2vh] wall:text-[2vh]">
                        last {days} days
                    </span>
                </div>
            </div>
            {status && (
                <div className="self-start sm:self-auto">
                    <BranchPill
                        status={status}
                        branch={repository.defaultBranch}
                        compact
                    />
                </div>
            )}
        </div>
    );
}

function MetricSlide({
    slide,
    labels,
    granularity,
    bucketDays,
}: {
    slide: Extract<Slide, { kind: 'metric' }>;
    labels: string[];
    granularity: VelocityReport['period']['granularity'];
    bucketDays: number;
}) {
    const { metric } = slide;
    const reference = slide.chart.type === 'line' ? metric.value : null;
    const benchmark = bucketThresholds(metric, bucketDays);
    const offChart =
        benchmark &&
        slide.chart.series.some((entry) =>
            entry.values.some((value) => value !== null && value > 0),
        )
            ? offChartTier(
                  benchmark,
                  wallScale(slide.chart.series, metric.unit, reference).max,
              )
            : null;

    return (
        <div className="flex flex-1 flex-col gap-6 wall:min-h-0 wall:flex-row wall:gap-[4vw]">
            <div className="flex flex-col wall:w-[34%] wall:shrink-0">
                <h1 className="text-3xl font-semibold tracking-tight wall:text-[5.5vh]">
                    <MetricLabel metric={metric}>{slide.title}</MetricLabel>
                </h1>
                <div className="mt-1 text-base text-ink-muted wall:mt-[0.6vh] wall:text-[2.3vh]">
                    {slide.hint}
                </div>
                <div className="mt-4 text-7xl leading-none font-semibold tracking-tighter whitespace-nowrap wall:mt-[3vh] wall:text-[17vh]">
                    {formatMetric(metric.value, metric.unit)}
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2 wall:mt-[2vh] wall:gap-[1vh]">
                    <WallDelta metric={metric} />
                    <BenchmarkBadge
                        metric={metric}
                        label={slide.title}
                        hint={slide.hint}
                        className={WALL_BADGE}
                    />
                </div>

                <dl className="mt-6 grid grid-cols-3 gap-4 border-t border-line pt-4 wall:mt-auto wall:grid-cols-1 wall:gap-[2.4vh] wall:pt-[3vh]">
                    {slide.details.map((detail) => (
                        <div
                            key={detail.label}
                            className="min-w-0 wall:flex wall:items-baseline wall:justify-between wall:gap-[1.5vh]"
                        >
                            <dt className="truncate text-sm text-ink-muted wall:text-[2.2vh]">
                                <MetricLabel metric={detail.metric}>
                                    {detail.label}
                                </MetricLabel>
                            </dt>
                            <dd className="flex flex-wrap items-baseline gap-2 wall:shrink-0 wall:gap-[1.2vh]">
                                <span className="text-2xl font-semibold tracking-tight whitespace-nowrap wall:text-[4.2vh]">
                                    {formatMetric(
                                        detail.metric.value,
                                        detail.metric.unit,
                                    )}
                                </span>
                                <span className="hidden wall:inline">
                                    <WallDelta metric={detail.metric} />
                                </span>
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>

            <div className="flex flex-col wall:min-h-0 wall:flex-1">
                <div className="flex items-center justify-between gap-4 text-sm text-ink-muted wall:text-[2vh]">
                    <span className="flex items-center gap-3 wall:gap-[1.5vh]">
                        Per {granularity}
                        {benchmark && offChart && (
                            <Badge
                                tone={offChart === 'elite' ? 'good' : 'bad'}
                                className={WALL_BADGE}
                            >
                                All {TIER_LABELS[offChart]} ·{' '}
                                {offChart === 'elite'
                                    ? `≤ ${formatMetric(benchmark.elite, metric.unit, true)}`
                                    : `< ${formatMetric(benchmark.medium, metric.unit, true)}`}
                            </Badge>
                        )}
                    </span>
                    {slide.chart.series.length > 1 && (
                        <span className="flex gap-4 wall:gap-[2vh]">
                            {slide.chart.series.map((entry) => (
                                <span
                                    key={entry.label}
                                    className="flex items-center gap-1.5 wall:gap-[0.8vh]"
                                >
                                    <span
                                        className="size-2.5 rounded-sm wall:size-[1.4vh]"
                                        style={{ background: entry.color }}
                                    />
                                    {entry.label}
                                </span>
                            ))}
                        </span>
                    )}
                </div>
                <div className="mt-3 h-64 wall:mt-[2vh] wall:h-auto wall:min-h-0 wall:flex-1 wall:pb-[2vh]">
                    <WallChart
                        type={slide.chart.type}
                        labels={labels}
                        series={slide.chart.series}
                        unit={metric.unit}
                        reference={reference}
                        benchmark={benchmark}
                    />
                </div>
            </div>
        </div>
    );
}

function PanelSlide({
    title,
    hint,
    children,
}: {
    title: string;
    hint: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-1 flex-col wall:min-h-0">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                <h1 className="text-3xl font-semibold tracking-tight wall:text-[5.5vh]">
                    {title}
                </h1>
                <span className="text-base text-ink-muted wall:text-[2.3vh]">
                    {hint}
                </span>
            </div>
            <div className="mt-6 flex-1 rounded-xl border border-line bg-surface p-4 wall:mt-[3vh] wall:mb-[2vh] wall:min-h-0 wall:rounded-[1.5vh] wall:p-[4vh]">
                {children}
            </div>
        </div>
    );
}

/**
 * A chart sized for reading across a room: it fills its parent and scales its text with its height.
 * Line charts can mark the period's own value, so each bucket reads against it, and benchmark tiers
 * that fall within the axis get a line of their own.
 */
function WallChart({
    type,
    labels,
    series,
    unit,
    reference,
    benchmark,
}: {
    type: 'bar' | 'line';
    labels: string[];
    series: ChartSeries[];
    unit: MetricUnit;
    reference: number | null;
    benchmark?: Thresholds;
}) {
    const [ref, { width, height }] = useElementSize<HTMLDivElement>();

    const font = Math.max(11, Math.min(26, Math.round(height / 20)));
    const format = (value: number) =>
        value === 0 ? '0' : formatMetric(value, unit, true);
    const scale = wallScale(series, unit, reference);

    const padding = {
        top: font,
        right: font,
        bottom: font * 2,
        left: font * 4,
    };
    const plotWidth = Math.max(0, width - padding.left - padding.right);
    const plotHeight = Math.max(0, height - padding.top - padding.bottom);
    const count = labels.length;
    const band = count > 0 ? plotWidth / count : 0;
    const y = (value: number) =>
        padding.top + plotHeight - (value / scale.max) * plotHeight;
    const x = (index: number) => padding.left + band * index + band / 2;
    const labelEvery = Math.max(
        1,
        Math.ceil(count / Math.max(1, plotWidth / (font * 6))),
    );
    const hasData = series.some((entry) =>
        entry.values.some((value) => value !== null && value > 0),
    );

    const groupWidth = band * 0.72;
    const gap = series.length > 1 ? Math.max(2, band * 0.04) : 0;
    const barWidth = Math.max(
        1,
        (groupWidth - gap * (series.length - 1)) / series.length,
    );

    return (
        <div ref={ref} className="relative h-full w-full">
            {width > 0 && height > 0 && (
                <svg width={width} height={height} aria-hidden>
                    {scale.ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={padding.left}
                                x2={width - padding.right}
                                y1={y(tick)}
                                y2={y(tick)}
                                stroke={
                                    tick === 0
                                        ? 'var(--line-strong)'
                                        : 'var(--grid)'
                                }
                            />
                            <text
                                x={padding.left - font * 0.6}
                                y={y(tick)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-ink-muted tabular-nums"
                                style={{ fontSize: font }}
                            >
                                {format(tick)}
                            </text>
                        </g>
                    ))}

                    {labels.map((label, index) =>
                        index % labelEvery === 0 ? (
                            <text
                                key={label + index}
                                x={x(index)}
                                y={height - font * 0.4}
                                textAnchor="middle"
                                className="fill-ink-muted"
                                style={{ fontSize: font }}
                            >
                                {label}
                            </text>
                        ) : null,
                    )}

                    {benchmark &&
                        TIER_LINES.map(({ tier, color, opacity }) =>
                            benchmark[tier] > 0 &&
                            benchmark[tier] <= scale.max ? (
                                <g key={tier} opacity={opacity}>
                                    <line
                                        x1={padding.left}
                                        x2={width - padding.right}
                                        y1={y(benchmark[tier])}
                                        y2={y(benchmark[tier])}
                                        stroke={color}
                                        strokeDasharray={`${font / 2} ${font / 3}`}
                                        strokeWidth={Math.max(1, font / 12)}
                                    />
                                    <text
                                        x={padding.left + font * 0.4}
                                        y={y(benchmark[tier]) - font * 0.4}
                                        fill={color}
                                        style={{ fontSize: font }}
                                    >
                                        {TIER_LABELS[tier]}
                                    </text>
                                </g>
                            ) : null,
                        )}

                    {type === 'bar' &&
                        series.map((entry, position) =>
                            entry.values.map((value, index) =>
                                value ? (
                                    <rect
                                        key={`${entry.label}-${index}`}
                                        x={
                                            x(index) -
                                            groupWidth / 2 +
                                            position * (barWidth + gap)
                                        }
                                        y={y(value)}
                                        width={barWidth}
                                        height={y(0) - y(value)}
                                        rx={Math.min(4, barWidth / 4)}
                                        fill={entry.color}
                                    />
                                ) : null,
                            ),
                        )}

                    {type === 'line' &&
                        series.map((entry) => (
                            <g key={entry.label}>
                                <path
                                    d={linePath(entry.values, x, y)}
                                    fill="none"
                                    stroke={entry.color}
                                    strokeWidth={Math.max(2, font / 5)}
                                    strokeLinejoin="round"
                                    strokeLinecap="round"
                                />
                                {entry.values.map((value, index) =>
                                    value !== null ? (
                                        <circle
                                            key={index}
                                            cx={x(index)}
                                            cy={y(value)}
                                            r={Math.max(3, font / 4)}
                                            fill={entry.color}
                                        />
                                    ) : null,
                                )}
                            </g>
                        ))}

                    {reference !== null && reference > 0 && (
                        <g>
                            <line
                                x1={padding.left}
                                x2={width - padding.right}
                                y1={y(reference)}
                                y2={y(reference)}
                                stroke="var(--ink-secondary)"
                                strokeDasharray={`${font / 2} ${font / 3}`}
                                strokeWidth={Math.max(1, font / 12)}
                            />
                            <text
                                x={width - padding.right}
                                y={y(reference) - font * 0.4}
                                textAnchor="end"
                                className="fill-ink-secondary font-semibold"
                                style={{ fontSize: font }}
                            >
                                period {format(reference)}
                            </text>
                        </g>
                    )}
                </svg>
            )}

            {!hasData && width > 0 && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-base text-ink-muted wall:text-[2.4vh]">
                    No data in this period
                </div>
            )}
        </div>
    );
}

/**
 * The wall chart's axis, which also fits the period's reference value.
 */
function wallScale(
    series: ChartSeries[],
    unit: MetricUnit,
    reference: number | null,
): { max: number; ticks: number[] } {
    const dataMax = Math.max(
        0,
        reference ?? 0,
        ...series.flatMap((entry) =>
            entry.values.filter((value): value is number => value !== null),
        ),
    );

    return unit === 'duration'
        ? niceDurationScale(dataMax)
        : niceScale(unit === 'ratio' ? Math.min(1, dataMax) : dataMax);
}

/**
 * Connect the points that have a value, breaking the line over buckets without one.
 */
function linePath(
    values: (number | null)[],
    x: (index: number) => number,
    y: (value: number) => number,
): string {
    let path = '';
    let drawing = false;

    values.forEach((value, index) => {
        if (value === null) {
            drawing = false;

            return;
        }

        path += `${drawing ? 'L' : 'M'}${x(index)},${y(value)} `;
        drawing = true;
    });

    return path.trim();
}
