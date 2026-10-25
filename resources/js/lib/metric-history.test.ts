import { describe, expect, it } from 'vite-plus/test';
import type { Metric } from '@/types';
import { metricHistory } from './metric-history';

const metric = (unit: Metric['unit'], statistic?: 'median'): Metric => ({
    value: 1,
    previous: null,
    unit,
    better: 'neutral',
    statistic,
});

const period = { bucketDays: 1, to: '2026-10-03', granularity: 'day' as const };
const trend = [
    { date: '2026-10-01', ciRuns: 4, ciFailureRate: 0.25 },
    { date: '2026-10-02', ciRuns: 2, ciFailureRate: null },
];

describe('metricHistory', () => {
    it('pairs each metric with its series, or the trend for CI runs and failure rate', () => {
        const kpis = {
            throughput: metric('count'),
            cycleTime: metric('duration', 'median'),
            ciRuns: metric('count'),
            ciFailureRate: metric('ratio'),
        };

        const { timelines, dates } = metricHistory({
            kpis,
            series: { throughput: [3, 1], cycleTime: [null, 3600] },
            trend,
            period,
        });

        expect(dates).toEqual(['2026-10-01', '2026-10-02']);
        expect(timelines.get(kpis.throughput)).toEqual({
            values: [3, 1],
            kind: 'bar',
        });
        expect(timelines.get(kpis.cycleTime)).toEqual({
            values: [null, 3600],
            kind: 'line',
        });
        expect(timelines.get(kpis.ciRuns)?.values).toEqual([4, 2]);
        expect(timelines.get(kpis.ciFailureRate)).toEqual({
            values: [0.25, null],
            kind: 'line',
        });
    });

    it('draws open pull requests as a line and leaves out metrics without history', () => {
        const kpis = {
            openPullRequests: metric('count'),
            stalePullRequests: metric('count'),
            ciDuration: metric('duration', 'median'),
            opened: metric('count'),
        };

        const { timelines } = metricHistory({
            kpis,
            // A series of the wrong length, as from a report built before it was bucketed this way.
            series: { openPullRequests: [5, 6], opened: [1] },
            trend,
            period,
        });

        expect(timelines.get(kpis.openPullRequests)?.kind).toBe('line');
        expect(timelines.has(kpis.stalePullRequests)).toBe(false);
        expect(timelines.has(kpis.ciDuration)).toBe(false);
        expect(timelines.has(kpis.opened)).toBe(false);
    });
});
