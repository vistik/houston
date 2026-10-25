import type { TimeSeriesChartProps } from '@/components/charts/time-series-chart';
import { bucketThresholds } from '@/lib/benchmarks';
import {
    formatDate,
    formatDuration,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { bucketLabel } from '@/lib/periods';
import type { VelocityReport } from '@/types';

/**
 * The trend charts of a repository report, shared by its views, with the bucket labels and start
 * days they use.
 */
export function trendCharts(report: VelocityReport) {
    const { kpis, trend, period } = report;
    const labels = trend.map((point) => formatDate(point.date));
    const tooltipLabels = trend.map((point) => bucketLabel(point.date, period));
    const dates = trend.map((point) => point.date);

    // Each trend chart's props, shared with the note that rates it when its benchmark lines are off the axis.
    const charts = {
        throughput: {
            kind: 'bar',
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.throughput, period.bucketDays),
            series: [
                {
                    key: 'merged',
                    label: 'Merged',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.merged),
                },
                {
                    key: 'opened',
                    label: 'Opened',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.opened),
                },
            ],
            format: (value) => formatNumber(value),
        },
        openPullRequests: {
            kind: 'line',
            labels,
            tooltipLabels,
            dates,
            series: [
                {
                    key: 'open',
                    label: 'Ready for review',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.open),
                },
                {
                    key: 'openDrafts',
                    label: 'Drafts',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.openDrafts),
                },
            ],
            format: (value) => formatNumber(value),
        },
        timeToMerge: {
            kind: 'line',
            durationAxis: true,
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.cycleTime, period.bucketDays),
            series: [
                {
                    key: 'cycle',
                    label: 'Time to merge',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.cycleTime),
                },
            ],
            format: formatDuration,
        },
        pickupTime: {
            kind: 'line',
            durationAxis: true,
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.pickupTime, period.bucketDays),
            series: [
                {
                    key: 'pickup',
                    label: 'Pickup time',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.pickupTime),
                },
            ],
            format: formatDuration,
        },
        ciFailureRate: {
            kind: 'line',
            labels,
            tooltipLabels,
            dates,
            maxValue: Math.max(
                0.1,
                ...trend.map((point) => point.ciFailureRate ?? 0),
            ),
            benchmark: bucketThresholds(kpis.ciFailureRate, period.bucketDays),
            series: [
                {
                    key: 'ci',
                    label: 'Failure rate',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.ciFailureRate),
                },
            ],
            format: formatPercent,
        },
    } satisfies Record<string, TimeSeriesChartProps>;

    return { labels, tooltipLabels, dates, charts };
}
