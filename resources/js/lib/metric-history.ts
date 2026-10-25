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
};

export const MetricHistoryContext = createContext<MetricHistory | null>(null);

/**
 * Metrics charted from the trend rather than the per-metric series, since the trend already counts
 * them per bucket.
 */
const FROM_TREND = ['ciRuns', 'ciFailureRate'] as const;

/** Counts of how many there are at a moment rather than how many happened, so they read as a line. */
const LEVELS = ['openPullRequests'];

/**
 * Pair each of a report's metrics with its values per bucket. Metrics without them, like stale pull
 * requests (only known right now) or CI durations (not broken down per bucket), get no timeline.
 */
export function metricHistory({
    kpis,
    series,
    trend,
    period,
}: {
    kpis: Partial<Record<string, Metric>>;
    series?: Partial<Record<string, (number | null)[]>>;
    trend: ({ date: string } & Partial<
        Record<(typeof FROM_TREND)[number], number | null>
    >)[];
    period: MetricHistory['period'];
}): MetricHistory {
    const timelines = new WeakMap<object, MetricTimeline>();

    for (const [key, metric] of Object.entries(kpis)) {
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

        timelines.set(metric, {
            values,
            kind:
                metric.unit === 'count' &&
                !metric.statistic &&
                !LEVELS.includes(key)
                    ? 'bar'
                    : 'line',
        });
    }

    return { dates: trend.map((point) => point.date), period, timelines };
}
