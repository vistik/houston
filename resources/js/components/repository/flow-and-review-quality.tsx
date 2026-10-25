import { useState } from 'react';
import type { TimeSeriesChartProps } from '@/components/charts/time-series-chart';
import { ChartPanel, MetricList, PanelCell } from '@/components/report/widgets';
import type { MetricListItem } from '@/components/report/widgets';
import { bucketThresholds } from '@/lib/benchmarks';
import { formatDuration, formatMetric, formatPercent } from '@/lib/format';
import type { Metric, PullRequestMetricKey, PullRequestReport } from '@/types';

type Row = {
    key: PullRequestMetricKey;
    label: string;
    hint: string;
    bars?: boolean;
};

function flowRows(defaultBranch: string | null): Row[] {
    return [
        {
            key: 'waitingOnReviewer',
            label: 'Waiting on reviewers',
            hint: 'Per merged PR, median',
        },
        {
            key: 'waitingOnAuthor',
            label: 'Waiting on the author',
            hint: 'After feedback, median',
        },
        {
            key: 'reviewRounds',
            label: 'Review rounds',
            hint: 'Times a PR went back to its author',
        },
        { key: 'leadTime', label: 'Lead time', hint: 'First commit → merged' },
        {
            key: 'codingTime',
            label: 'Coding time',
            hint: 'First commit → PR opened',
        },
        {
            key: 'timeToApproval',
            label: 'Time to approval',
            hint: 'Ready → first approval',
        },
        { key: 'mergeDelay', label: 'Merge delay', hint: 'Approval → merged' },
        {
            key: 'cycleTimeP90',
            label: 'Time to merge (p90)',
            hint: 'The slow tail',
        },
        {
            key: 'mergeFrequency',
            label: 'Merge frequency',
            hint: `Into ${defaultBranch ?? 'default branch'}`,
        },
    ];
}

const REVIEW_QUALITY_ROWS: Row[] = [
    { key: 'pullRequestSize', label: 'PR size', hint: 'Median lines changed' },
    {
        key: 'unreviewedMergeRate',
        label: 'Merged without review',
        hint: 'No approving or commenting review',
    },
    {
        key: 'changesRequestedRate',
        label: 'Changes requested',
        hint: 'Share of merged PRs',
    },
    {
        key: 'reviewDepth',
        label: 'Review depth',
        hint: 'Comments per merged PR',
    },
    {
        key: 'reworkRate',
        label: 'Rework after review',
        hint: 'Reviewed PRs with follow-up pushes',
    },
    {
        key: 'reviewConcentration',
        label: 'Review concentration',
        hint: 'Share of reviews by the busiest reviewer',
    },
    {
        key: 'remediations',
        label: 'Reverts & hotfixes',
        hint: 'Merged this period',
        bars: true,
    },
    {
        key: 'mergeRate',
        label: 'Merge rate',
        hint: 'Merged vs closed unmerged',
    },
    {
        key: 'opened',
        label: 'PRs opened',
        hint: 'New work started',
        bars: true,
    },
];

/**
 * Secondary flow and review metrics as headline numbers, each also charted across the period, as
 * grid cells for a report section.
 */
export function FlowAndReviewQuality({
    report,
    defaultBranch,
    labels,
    tooltipLabels,
    dates,
}: {
    report: PullRequestReport;
    defaultBranch: string | null;
    labels: string[];
    tooltipLabels: string[];
    dates: string[];
}) {
    const rows = [...flowRows(defaultBranch), ...REVIEW_QUALITY_ROWS];
    const [selectedKey, setSelectedKey] =
        useState<PullRequestMetricKey>('waitingOnReviewer');
    const selected = rows.find((row) => row.key === selectedKey) ?? rows[0];
    const chart = metricChart({
        metric: report.kpis[selected.key],
        bars: selected.bars ?? false,
        values: report.series[selected.key] ?? [],
        label: selected.label,
        labels,
        tooltipLabels,
        dates,
        bucketDays: report.period.bucketDays,
    });

    const items = (rows: Row[]): MetricListItem<PullRequestMetricKey>[] =>
        rows.map((row) => ({
            key: row.key,
            label: row.label,
            hint: row.hint,
            metric: report.kpis[row.key],
            series: report.series[row.key],
        }));

    return (
        <>
            <MetricList
                span="1/3"
                title="Flow"
                items={items(flowRows(defaultBranch))}
                selected={selected.key}
                onSelect={setSelectedKey}
            />
            <MetricList
                span="1/3"
                title="Review quality"
                items={items(REVIEW_QUALITY_ROWS)}
                selected={selected.key}
                onSelect={setSelectedKey}
            />
            <PanelCell
                span="1/3"
                title="Size vs speed"
                description="Smaller PRs should land faster"
            >
                <SizeBreakdown sizes={report.sizes} />
            </PanelCell>
            <ChartPanel
                span="full"
                title={selected.label}
                metric={report.kpis[selected.key]}
                description={`${selected.hint} · per ${report.period.granularity}`}
                chart={chart}
            />
        </>
    );
}

/**
 * How a metric charts over the period: durations and ratios as lines on their own axes.
 */
function metricChart({
    metric,
    bars,
    values,
    label,
    labels,
    tooltipLabels,
    dates,
    bucketDays,
}: {
    metric: Metric;
    bars: boolean;
    values: (number | null)[];
    label: string;
    labels: string[];
    tooltipLabels: string[];
    dates: string[];
    bucketDays: number;
}): TimeSeriesChartProps {
    const chart = {
        labels,
        tooltipLabels,
        dates,
        benchmark: bucketThresholds(metric, bucketDays),
        series: [{ key: 'metric', label, color: 'var(--series-1)', values }],
    };

    if (metric.unit === 'duration') {
        return {
            ...chart,
            kind: 'line',
            durationAxis: true,
            format: formatDuration,
        };
    }

    if (metric.unit === 'ratio') {
        return {
            ...chart,
            kind: 'line',
            maxValue: Math.max(0.1, ...values.map((value) => value ?? 0)),
            format: formatPercent,
        };
    }

    return {
        ...chart,
        kind: bars ? 'bar' : 'line',
        format: (value) => formatMetric(value, metric.unit),
    };
}

function SizeBreakdown({ sizes }: { sizes: PullRequestReport['sizes'] }) {
    const maxCount = Math.max(1, ...sizes.map((size) => size.count));
    const ranges: Record<string, string> = {
        XS: '≤ 10',
        S: '≤ 100',
        M: '≤ 400',
        L: '≤ 1k',
        XL: '> 1k',
    };

    return (
        <div className="space-y-3">
            {sizes.map((size) => (
                <div
                    key={size.label}
                    className="grid grid-cols-[2.5rem_1fr_auto] items-center gap-3"
                >
                    <div>
                        <div className="text-sm font-semibold">
                            {size.label}
                        </div>
                        <div className="text-[10px] text-ink-muted">
                            {ranges[size.label]}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <div
                            className="h-2 rounded-full bg-series-1"
                            style={{
                                width: `${Math.max(2, (size.count / maxCount) * 100)}%`,
                                opacity: size.count ? 1 : 0.25,
                            }}
                        />
                        <span className="tabular text-xs text-ink-secondary">
                            {size.count}
                        </span>
                    </div>
                    <div className="tabular w-14 text-right text-sm">
                        {formatDuration(size.cycleTime)}
                    </div>
                </div>
            ))}
            <div className="flex justify-between border-t border-line pt-2 text-[11px] text-ink-muted">
                <span>Lines changed · merged PRs</span>
                <span>Median time to merge</span>
            </div>
        </div>
    );
}
