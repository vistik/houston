import { Link } from '@inertiajs/react';
import { Children, isValidElement, use } from 'react';
import type { ComponentProps, ReactElement } from 'react';
import {
    AlertIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    XIcon,
} from '@/components/icons';
import { MetricLabel } from '@/components/metric-card';
import { Cell } from '@/components/report/grid';
import type { MetricTile } from '@/components/report/widgets';
import { formatDate, formatMetric, metricDelta } from '@/lib/format';
import { periodDays, periodLabel, previousPeriod } from '@/lib/periods';
import { ReportPeriod } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { TimelineEvents } from '@/lib/timeline-events';
import { cn } from '@/lib/utils';
import type { FocusedTimelineEvent, Metric } from '@/types';

/**
 * The headline tiles at the top of a report view. While the report compares before and after a
 * timeline event, the same metrics show as that comparison instead, so each view compares what it
 * leads with. Right-now metrics have no before, so they are left out of it.
 */
export function Headline({
    children,
}: {
    children: ReactElement<ComponentProps<typeof MetricTile>>[];
}) {
    const timeline = use(TimelineEvents);
    const period = use(ReportPeriod);

    if (!timeline?.focused || !period) {
        return children;
    }

    return (
        <Cell>
            <EventComparison
                event={timeline.focused}
                period={period}
                clearHref={timeline.clearHref}
                metrics={Children.toArray(children)
                    .filter(isValidElement<ComponentProps<typeof MetricTile>>)
                    .filter(({ props }) => !props.metric.snapshot)
                    .map(({ props }) => ({
                        key: props.label,
                        label: props.label,
                        metric: props.metric,
                    }))}
            />
        </Cell>
    );
}

// A column per metric on wide screens, up to six, so the row is always full.
const WIDE_COLUMNS: Record<number, string> = {
    1: 'lg:grid-cols-1',
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
    5: 'lg:grid-cols-5',
    6: 'lg:grid-cols-6',
};

export type ComparedMetric = {
    key: string;
    label: string;
    metric: Metric;
};

/**
 * Heads a report that runs from a timeline event to today: the headline metrics before and after
 * the event side by side, over equally long stretches, and whether each got better or worse.
 */
export function EventComparison({
    event,
    period,
    metrics,
    clearHref,
}: {
    event: FocusedTimelineEvent;
    period: PeriodFilters;
    metrics: ComparedMetric[];
    /** The report on its usual period. */
    clearHref: string;
}) {
    const days = periodDays(period);
    const previous = previousPeriod(period);
    const startsLate = period.from !== null && period.from > event.date;
    const stretch = `${days} day${days === 1 ? '' : 's'}`;
    const tones = metrics.map(({ metric }) => metricDelta(metric)?.tone);
    const improved = tones.filter((tone) => tone === 'good').length;
    const worsened = tones.filter((tone) => tone === 'bad').length;

    return (
        <section className="overflow-hidden rounded-xl border border-line bg-surface shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
            <header className="flex items-start gap-4 px-5 pt-4">
                <div className="min-w-0 flex-1">
                    <div className="text-[11px] font-medium tracking-wide text-ink-muted uppercase">
                        Before and after · {formatDate(event.date, true)}
                    </div>
                    <h2 className="mt-0.5 text-lg font-semibold tracking-tight text-ink">
                        {event.title}
                    </h2>
                    <p className="mt-1 text-sm text-ink-secondary">
                        The {stretch} since ({periodLabel(period)}) against the{' '}
                        {stretch} before ({periodLabel(previous)})
                        {metrics.length > 0 &&
                            ` · ${improved} of ${metrics.length} got better, ${worsened} got worse`}
                        .
                    </p>
                    {(startsLate || previous.from < event.historyFrom) && (
                        <p className="mt-2 flex items-center gap-1.5 text-xs text-warn">
                            <AlertIcon width={13} height={13} />
                            {startsLate
                                ? `Houston has synced history from ${formatDate(event.historyFrom, true)}, so this starts there instead of on the day of the event.`
                                : `Houston has synced history from ${formatDate(event.historyFrom, true)}, so the ${stretch} before are only partly covered.`}
                        </p>
                    )}
                </div>
                <Link
                    href={clearHref}
                    aria-label="Back to the usual period"
                    title="Back to the usual period"
                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-line/60 hover:text-ink"
                >
                    <XIcon width={14} height={14} />
                </Link>
            </header>

            <div
                className={cn(
                    'mt-4 grid grid-cols-2 border-t border-line sm:grid-cols-3',
                    WIDE_COLUMNS[Math.min(metrics.length, 6)],
                )}
            >
                {metrics.map(({ key, label, metric }) => (
                    <Comparison key={key} label={label} metric={metric} />
                ))}
            </div>
        </section>
    );
}

function Comparison({ label, metric }: Omit<ComparedMetric, 'key'>) {
    const delta = metricDelta(metric);
    const Arrow = delta?.direction === 'up' ? ArrowUpIcon : ArrowDownIcon;

    return (
        <div className="-mr-px -mb-px flex min-w-0 flex-col gap-2 border-r border-b border-line px-5 py-4">
            <div className="truncate text-xs font-medium text-ink-secondary">
                <MetricLabel metric={metric}>{label}</MetricLabel>
            </div>
            <div className="flex items-baseline gap-2">
                <span className="tabular text-sm text-ink-muted">
                    {formatMetric(metric.previous, metric.unit)}
                </span>
                <span className="text-ink-muted">→</span>
                <span className="tabular text-2xl font-semibold tracking-tight text-ink">
                    {formatMetric(metric.value, metric.unit)}
                </span>
            </div>
            {delta ? (
                <span
                    className={cn(
                        'inline-flex items-center gap-1 self-start rounded-full px-2 py-0.5 text-[11px] font-medium',
                        delta.tone === 'good' && 'bg-good-soft text-good',
                        delta.tone === 'bad' && 'bg-bad-soft text-bad',
                        delta.tone === 'neutral' &&
                            'bg-line/70 text-ink-secondary',
                    )}
                >
                    {delta.direction === 'flat' ? (
                        'No change'
                    ) : (
                        <>
                            <Arrow width={11} height={11} />
                            {delta.label}
                            {delta.tone === 'good'
                                ? ' better'
                                : delta.tone === 'bad'
                                  ? ' worse'
                                  : ''}
                        </>
                    )}
                </span>
            ) : (
                <span className="text-[11px] text-ink-muted">
                    Not enough data
                </span>
            )}
        </div>
    );
}
