import { createContext } from 'react';
import type { Granularity } from '@/lib/periods';
import type { Metric } from '@/types';

/** How a metric is drawn over time: bars for what piles up per bucket, a line for everything else. */
export type HistoryKind = 'bar' | 'line';

export type MetricTimeline = {
    values: (number | null)[];
    kind: HistoryKind;
};

/**
 * Every metric of a report per trend bucket, for explaining a metric together with how it moved.
 */
export type MetricHistory = {
    /** Where each trend bucket starts. */
    dates: string[];
    period: { bucketDays: number; to: string; granularity: Granularity };
    /** Each metric's values per bucket, by the metric object the page renders. */
    timelines: WeakMap<object, MetricTimeline>;
    /** The page of each metric of its own, by the metric object the page renders, when it has one. */
    links: WeakMap<object, string>;
};

export const MetricHistoryContext = createContext<MetricHistory | null>(null);

/**
 * Metrics charted from the trend rather than the per-metric series, since the trend already counts
 * them per bucket.
 */
const FROM_TREND = ['ciRuns', 'ciFailureRate'] as const;

/** The metric each trend charts, where its key differs from the metric's. */
const TREND_METRICS: Record<string, string> = { timeToMerge: 'cycleTime' };

/** Counts of how many there are at a moment rather than how many happened, so they read as a line. */
const LEVELS = ['openPullRequests'];

/** Bars for counts of what happened per bucket, a line for everything else. */
export function historyKind(
    key: string,
    metric: Pick<Metric, 'unit' | 'statistic'>,
): HistoryKind {
    return metric.unit === 'count' && !metric.statistic && !LEVELS.includes(key)
        ? 'bar'
        : 'line';
}

/**
 * Pair each of a report's metrics with its values per bucket. Metrics without them, like stale pull
 * requests (only known right now) or CI durations (not broken down per bucket), get no timeline.
 * Pass `href` to link each metric, and each trend to the metric it charts, to a page of its own.
 */
export function metricHistory({
    kpis,
    series,
    trend,
    trends,
    period,
    href,
}: {
    kpis: Partial<Record<string, Metric>>;
    series?: Partial<Record<string, (number | null)[]>>;
    trend: ({ date: string } & Partial<
        Record<(typeof FROM_TREND)[number], number | null>
    >)[];
    trends?: Partial<Record<string, object>>;
    period: MetricHistory['period'];
    href?: (key: string) => string;
}): MetricHistory {
    const timelines = new WeakMap<object, MetricTimeline>();
    const links = new WeakMap<object, string>();

    for (const [key, chart] of Object.entries(trends ?? {})) {
        const metricKey = TREND_METRICS[key] ?? key;

        if (chart && href && kpis[metricKey]) {
            links.set(chart, href(metricKey));
        }
    }

    for (const [key, metric] of Object.entries(kpis)) {
        if (metric && href) {
            links.set(metric, href(key));
        }

        const values =
            series?.[key] ??
            (FROM_TREND.includes(key as (typeof FROM_TREND)[number])
                ? trend.map(
                      (point) =>
                          point[key as (typeof FROM_TREND)[number]] ?? null,
                  )
                : undefined);

        if (!metric || !values || values.length !== trend.length) {
            continue;
        }

        timelines.set(metric, { values, kind: historyKind(key, metric) });
    }

    return {
        dates: trend.map((point) => point.date),
        period,
        timelines,
        links,
    };
}
