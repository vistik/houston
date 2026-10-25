import { use } from 'react';
import type { ReactNode } from 'react';
import { MiniTrend } from '@/components/charts/mini-trend';
import { Sparkline } from '@/components/charts/sparkline';
import { ArrowDownIcon, ArrowUpIcon } from '@/components/icons';
import { MetricIcon } from '@/components/metric-icons';
import { HoverCard, Tooltip } from '@/components/ui/hover-card';
import type { HoverCardFocus } from '@/components/ui/hover-card';
import {
    TIER_LABELS,
    TIERS,
    describeThresholds,
    formatThreshold,
    isWeekly,
    nextTierGap,
    ratedValue,
    tierFor,
} from '@/lib/benchmarks';
import { formatDate, formatMetric, metricDelta } from '@/lib/format';
import { MetricHistoryContext } from '@/lib/metric-history';
import { TimelineEvents } from '@/lib/timeline-events';
import type { MetricHistory, MetricTimeline } from '@/lib/metric-history';
import {
    ReportPeriod,
    isCustomPeriod,
    periodDates,
    periodDays,
    periodLabel,
    previousPeriod,
} from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { cn } from '@/lib/utils';
import type {
    BenchmarkTier,
    Metric,
    MetricBenchmark,
    MetricKey,
    MetricStatistic,
} from '@/types';

export const TIER_TONES: Record<BenchmarkTier, string> = {
    elite: 'bg-good text-page',
    good: 'bg-good-soft text-good',
    medium: 'bg-warn-soft text-warn',
    low: 'bg-bad-soft text-bad',
};

const TIER_DOTS: Record<BenchmarkTier, string> = {
    elite: 'bg-good',
    good: 'bg-good/50',
    medium: 'bg-warn',
    low: 'bg-bad',
};

/**
 * Where the metric lands against its benchmark, explained on hover. Renders nothing for metrics
 * without one.
 */
export function BenchmarkBadge({
    metric,
    label,
    hint,
    className,
}: {
    metric: Metric;
    label?: string;
    hint?: string;
    className?: string;
}) {
    const { benchmark } = metric;

    if (!benchmark?.tier) {
        return null;
    }

    return (
        <HoverCard
            className="shrink-0"
            card={
                <BenchmarkCard
                    metric={metric}
                    benchmark={benchmark}
                    label={label}
                    hint={hint}
                />
            }
        >
            <span
                className={cn(
                    'inline-flex shrink-0 cursor-default items-center rounded-full px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                    TIER_TONES[benchmark.tier],
                    className,
                )}
                aria-label={`${TIER_LABELS[benchmark.tier]} · benchmark ${describeThresholds(benchmark, metric.unit, benchmark.perWeek)}`}
            >
                {TIER_LABELS[benchmark.tier]}
            </span>
        </HoverCard>
    );
}

function BenchmarkCard({
    metric,
    benchmark,
    label,
    hint,
}: {
    metric: Metric;
    benchmark: MetricBenchmark;
    label?: string;
    hint?: string;
}) {
    const period = use(ReportPeriod);
    const { unit, value, previous } = metric;
    const { tier, perWeek } = benchmark;
    const days = period ? periodDays(period) : null;
    const weekly = isWeekly(unit, perWeek);
    const sign = benchmark.direction === 'higher' ? '≥' : '≤';
    const delta = metricDelta(metric);

    if (value === null || tier === null) {
        return null;
    }

    // Per-week benchmarks can't be rated without knowing how long the period is.
    const rateable = !perWeek || days !== null;
    const rated = rateable ? ratedValue(value, perWeek, days ?? 1) : null;
    const previousTier =
        previous !== null && rateable
            ? tierFor(ratedValue(previous, perWeek, days ?? 1), benchmark)
            : null;
    const gap =
        rated === null
            ? null
            : nextTierGap(rated, tier, benchmark, unit, perWeek);

    const outcome = [
        tier === 'elite'
            ? `Meets the Elite target (${sign} ${formatThreshold(benchmark.elite, unit, perWeek)}).`
            : gap && `${gap}.`,
        previousTier &&
            (previousTier === tier
                ? `Also ${TIER_LABELS[tier]} last period.`
                : `${TIERS.indexOf(tier) < TIERS.indexOf(previousTier) ? 'Up' : 'Down'} from ${TIER_LABELS[previousTier]} last period.`),
        delta &&
            (delta.direction === 'flat'
                ? 'Unchanged from the previous period.'
                : `${delta.direction === 'up' ? 'Up' : 'Down'} ${delta.label}${delta.tone === 'neutral' ? '' : delta.tone === 'good' ? ', an improvement' : ', a step back'}.`),
    ].filter(Boolean);

    return (
        <div className="flex flex-col gap-2.5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="font-medium text-ink">
                        {label ?? 'Benchmark'}
                    </div>
                    {hint && (
                        <div className="text-[11px] text-ink-muted">{hint}</div>
                    )}
                </div>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-1.5 py-0.5 text-[11px] font-medium',
                        TIER_TONES[tier],
                    )}
                >
                    {TIER_LABELS[tier]}
                </span>
            </div>

            <div className="flex flex-col gap-1 border-t border-line pt-2.5">
                <CardRow
                    label="This period"
                    detail={period ? currentPeriodLabel(period) : undefined}
                    value={formatMetric(value, unit)}
                    aside={
                        weekly && rated !== null
                            ? `≈ ${formatThreshold(rated, unit, perWeek)}`
                            : undefined
                    }
                />
                <CardRow
                    label="Previous period"
                    detail={
                        period ? periodLabel(previousPeriod(period)) : undefined
                    }
                    value={formatMetric(previous, unit)}
                    aside={previousTier ? TIER_LABELS[previousTier] : undefined}
                />
            </div>

            <div className="border-t border-line pt-2.5">
                <div className="mb-1 text-[11px] text-ink-muted">
                    {benchmark.direction === 'higher' ? 'Higher' : 'Lower'} is
                    better{weekly ? ', rated per week' : ''}
                </div>
                {TIERS.map((step) => (
                    <div
                        key={step}
                        className={cn(
                            '-mx-1.5 flex items-center justify-between rounded-md px-1.5 py-0.5',
                            step === tier && 'bg-line/60 font-medium text-ink',
                        )}
                    >
                        <span className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    TIER_DOTS[step],
                                )}
                            />
                            {TIER_LABELS[step]}
                            {step === tier && (
                                <span className="text-[11px] font-normal text-ink-muted">
                                    · here
                                </span>
                            )}
                        </span>
                        <span className="tabular">
                            {step === 'low'
                                ? `${benchmark.direction === 'higher' ? '<' : '>'} ${formatThreshold(benchmark.medium, unit, perWeek)}`
                                : `${sign} ${formatThreshold(benchmark[step], unit, perWeek)}`}
                        </span>
                    </div>
                ))}
            </div>

            {outcome.length > 0 && (
                <p className="border-t border-line pt-2.5 leading-relaxed text-ink">
                    {outcome.join(' ')}
                </p>
            )}
        </div>
    );
}

function CardRow({
    label,
    detail,
    value,
    aside,
}: {
    label: string;
    detail?: string;
    value: string;
    aside?: string;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <span className="min-w-0">
                {label}
                {detail && (
                    <span className="block text-[11px] text-ink-muted">
                        {detail}
                    </span>
                )}
            </span>
            <span className="shrink-0 text-right">
                <span className="tabular font-medium text-ink">{value}</span>
                {aside && (
                    <span className="block text-[11px] text-ink-muted">
                        {aside}
                    </span>
                )}
            </span>
        </div>
    );
}

function currentPeriodLabel(period: PeriodFilters): string {
    return isCustomPeriod(period)
        ? periodLabel(period)
        : `${capitalize(periodLabel(period))} · ${periodLabel(periodDates(period))}`;
}

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export function DeltaPill({ metric }: { metric: Metric }) {
    const period = use(ReportPeriod);
    const delta = metricDelta(metric);

    if (!delta) {
        return null;
    }

    const Arrow = delta.direction === 'up' ? ArrowUpIcon : ArrowDownIcon;

    return (
        <Tooltip
            className="shrink-0"
            content={
                <>
                    <div>
                        <span className="font-medium">
                            {formatMetric(metric.value, metric.unit)}
                        </span>{' '}
                        vs{' '}
                        <span className="font-medium">
                            {formatMetric(metric.previous, metric.unit)}
                        </span>{' '}
                        the period before
                        {delta.tone !== 'neutral' &&
                            (delta.tone === 'good'
                                ? ', an improvement'
                                : ', a step back')}
                    </div>
                    {period && (
                        <div className="text-ink-muted">
                            {periodLabel(previousPeriod(period))}
                        </div>
                    )}
                </>
            }
        >
            <span
                className={cn(
                    'inline-flex shrink-0 cursor-default items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[11px] font-medium whitespace-nowrap',
                    delta.tone === 'good' && 'bg-good-soft text-good',
                    delta.tone === 'bad' && 'bg-bad-soft text-bad',
                    delta.tone === 'neutral' && 'bg-line/70 text-ink-secondary',
                )}
            >
                {delta.direction !== 'flat' && (
                    <Arrow width={11} height={11} strokeWidth={2.25} />
                )}
                {delta.direction === 'flat' ? 'no change' : delta.label}
            </span>
        </Tooltip>
    );
}

const STATISTIC_LABELS: Record<MetricStatistic, string> = {
    median: 'Median',
    average: 'Average',
};

/**
 * The statistic a metric's value isn't, like the average of a metric reported as a median. Null for
 * metrics that don't sum up one value per item.
 */
function otherStatistic(
    metric: Pick<Metric, 'statistic' | 'median' | 'average'>,
): { label: string; value: number | null } | null {
    if (!metric.statistic) {
        return null;
    }

    const other = metric.statistic === 'median' ? 'average' : 'median';

    return { label: STATISTIC_LABELS[other], value: metric[other] ?? null };
}

const GRANULARITY_LABELS: Record<
    MetricHistory['period']['granularity'],
    string
> = {
    day: 'Per day',
    week: 'Per week',
    month: 'Per 30 days',
};

/**
 * A metric across the report's trend buckets, as a small chart with its lowest, highest and latest
 * value spelled out, so the card reads without seeing the chart.
 */
function MetricOverTime({
    timeline,
    history,
    unit,
}: {
    timeline: MetricTimeline;
    history: MetricHistory;
    unit: Metric['unit'];
}) {
    const { values, kind } = timeline;
    const known = values.filter((value): value is number => value !== null);
    const latest = values.findLastIndex((value) => value !== null);
    const { dates } = history;

    return (
        <div className="mt-1 flex flex-col gap-1.5 border-t border-line pt-2">
            <div className="flex items-baseline justify-between gap-3">
                <span className="font-medium text-ink">Over time</span>
                <span className="text-[11px] text-ink-muted">
                    {GRANULARITY_LABELS[history.period.granularity]}
                </span>
            </div>
            <div>
                <MiniTrend values={values} kind={kind} />
                <div
                    className="mt-1 flex justify-between text-[10px] text-ink-muted"
                    aria-hidden
                >
                    <span>{formatDate(dates[0])}</span>
                    <span>{formatDate(dates[dates.length - 1])}</span>
                </div>
            </div>
            <dl className="grid grid-cols-3 gap-2">
                {[
                    { label: 'Lowest', value: Math.min(...known) },
                    { label: 'Highest', value: Math.max(...known) },
                    {
                        label: `Latest · ${formatDate(dates[latest])}`,
                        value: values[latest],
                    },
                ].map((stat, index) => (
                    <div
                        key={stat.label}
                        className={cn('min-w-0', index === 2 && 'text-right')}
                    >
                        <dt className="truncate text-[11px] text-ink-muted">
                            {stat.label}
                        </dt>
                        <dd className="tabular font-medium text-ink">
                            {formatMetric(stat.value, unit)}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

/**
 * A metric's or trend's title, explaining on hover or keyboard focus how it is calculated, the median
 * and average this period for metrics that sum up one value per item, and how it moved across the
 * period when the page provides a MetricHistoryContext that knows it.
 *
 * Pass `focus="control"` when the label sits inside a link or button, so focusing that shows the card.
 */
export function MetricLabel({
    metric,
    children,
    className,
    focus = 'self',
}: {
    metric: Pick<Metric, 'description'> &
        Partial<Pick<Metric, 'unit' | 'statistic' | 'median' | 'average'>> & {
            summary?: string;
        };
    children: ReactNode;
    className?: string;
    focus?: HoverCardFocus;
}) {
    const history = use(MetricHistoryContext);

    if (!metric.description) {
        return children;
    }

    const { statistic, unit } = metric;
    const timeline = history?.timelines.get(metric);
    // A trend needs at least two buckets with a value to show anything.
    const showsHistory =
        history &&
        timeline &&
        unit &&
        timeline.values.filter((value) => value !== null).length >= 2;

    return (
        <HoverCard
            className={cn(
                'max-w-full rounded-sm focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none',
                className,
            )}
            panelClassName="w-72 rounded-xl p-3"
            focus={focus}
            card={
                <div className="flex flex-col gap-1">
                    <div className="font-medium text-ink">{children}</div>
                    {metric.summary && (
                        <p className="text-ink-secondary">{metric.summary}</p>
                    )}
                    <p className="leading-relaxed">{metric.description}</p>
                    {statistic && unit && (
                        <div className="mt-1 flex flex-col gap-0.5 border-t border-line pt-2">
                            {(['median', 'average'] as const).map((key) => (
                                <div
                                    key={key}
                                    className="flex items-baseline justify-between gap-3"
                                >
                                    <span>
                                        {STATISTIC_LABELS[key]} this period
                                        {key === statistic && (
                                            <span className="text-[11px] text-ink-muted">
                                                {' '}
                                                · shown
                                            </span>
                                        )}
                                    </span>
                                    <span className="tabular font-medium text-ink">
                                        {formatMetric(metric[key], unit)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                    {showsHistory && (
                        <MetricOverTime
                            timeline={timeline}
                            history={history}
                            unit={unit}
                        />
                    )}
                </div>
            }
        >
            <span className="cursor-help truncate underline decoration-line-strong decoration-dotted underline-offset-[3px]">
                {children}
            </span>
        </HoverCard>
    );
}

export function MetricCard({
    label,
    hint,
    metric,
    icon,
    size = 'md',
    footer,
}: {
    label: string;
    hint?: string;
    metric: Metric;
    /** The metric whose icon sits before the label. */
    icon?: MetricKey;
    size?: 'md' | 'lg';
    footer?: ReactNode;
}) {
    const other = otherStatistic(metric);
    // Reports that compare before and after an event say so rather than "previous period".
    const comparedWith = use(TimelineEvents)?.focused
        ? 'before'
        : 'previous period';

    return (
        <div className="flex min-w-0 flex-col rounded-xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5 text-xs font-medium text-ink-secondary">
                        {icon && (
                            <MetricIcon
                                metric={icon}
                                width={15}
                                height={15}
                                className="shrink-0 text-ink-muted"
                            />
                        )}
                        <MetricLabel metric={metric} className="min-w-0">
                            {label}
                        </MetricLabel>
                    </div>
                    {hint && (
                        <div className="text-[11px] text-ink-muted">{hint}</div>
                    )}
                </div>
                <DeltaPill metric={metric} />
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                <span
                    className={cn(
                        'font-semibold tracking-tight text-ink',
                        size === 'lg' ? 'text-3xl' : 'text-2xl',
                    )}
                >
                    {formatMetric(metric.value, metric.unit)}
                </span>
                <BenchmarkBadge metric={metric} label={label} hint={hint} />
            </div>
            {metric.previous !== null && (
                <div className="mt-1 text-[11px] text-ink-muted">
                    vs {formatMetric(metric.previous, metric.unit)}{' '}
                    {comparedWith}
                </div>
            )}
            {other && (
                <div
                    className={cn(
                        'text-[11px] text-ink-muted',
                        metric.previous === null && 'mt-1',
                    )}
                >
                    {other.label} {formatMetric(other.value, metric.unit)}
                </div>
            )}
            {footer && <div className="mt-3">{footer}</div>}
        </div>
    );
}

/**
 * Dense row layout for secondary metrics. Pass `series` to show the metric over time, and
 * `onSelect` to make the row pickable.
 */
export function MetricRow({
    label,
    hint,
    metric,
    icon,
    series,
    selected = false,
    onSelect,
}: {
    label: string;
    hint?: string;
    metric: Metric;
    /** The metric whose icon sits before the label. */
    icon?: MetricKey;
    series?: (number | null)[];
    selected?: boolean;
    onSelect?: () => void;
}) {
    const content = (
        <>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5 text-sm text-ink">
                    {icon && (
                        <MetricIcon
                            metric={icon}
                            width={15}
                            height={15}
                            className="shrink-0 text-ink-muted"
                        />
                    )}
                    <MetricLabel
                        metric={metric}
                        focus={onSelect ? 'control' : 'self'}
                        className="min-w-0"
                    >
                        {label}
                    </MetricLabel>
                </div>
                {hint && (
                    <div className="truncate text-[11px] text-ink-muted">
                        {hint}
                    </div>
                )}
            </div>
            {series && (
                <Sparkline
                    values={series.filter(
                        (value): value is number => value !== null,
                    )}
                    color={selected ? 'var(--accent)' : 'var(--ink-muted)'}
                    height={20}
                    // In a list too narrow for it next to the label and values, the label gets the room.
                    className="w-12 shrink-0 @max-sm:hidden"
                />
            )}
            <div className="flex shrink-0 items-center gap-2">
                <BenchmarkBadge metric={metric} label={label} hint={hint} />
                <DeltaPill metric={metric} />
                <span className="tabular w-16 text-right text-sm font-semibold">
                    {formatMetric(metric.value, metric.unit)}
                </span>
            </div>
        </>
    );

    if (!onSelect) {
        return (
            <div className="flex items-center justify-between gap-3 py-2.5">
                {content}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                '-mx-2 flex w-[calc(100%+1rem)] items-center justify-between gap-3 rounded-lg px-2 py-2.5 text-left transition hover:bg-line/40 focus-visible:ring-2 focus-visible:ring-accent-soft focus-visible:outline-none',
                selected && 'bg-accent-soft hover:bg-accent-soft',
            )}
        >
            {content}
        </button>
    );
}
