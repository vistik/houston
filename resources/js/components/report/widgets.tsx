import type { ComponentProps, ReactNode } from 'react';
import {
    BenchmarkNote,
    TimeSeriesChart,
} from '@/components/charts/time-series-chart';
import type { TimeSeriesChartProps } from '@/components/charts/time-series-chart';
import { MetricCard, MetricLabel, MetricRow } from '@/components/metric-card';
import { Cell, Grid } from '@/components/report/grid';
import type { ResponsiveSpan, Span } from '@/components/report/grid';
import { Panel, SectionHeading } from '@/components/ui/panel';
import { cn } from '@/lib/utils';
import type { Metric, MetricKey } from '@/types';

type Spanned = { span?: Span | ResponsiveSpan };

/**
 * A headed block of a report, laying its widgets out on a 12 column grid.
 */
export function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section>
            <SectionHeading title={title} description={description} />
            <Grid>{children}</Grid>
        </section>
    );
}

/**
 * A headline number with its change and benchmark. Takes a quarter of the row unless told otherwise.
 */
export function MetricTile({
    span = '1/4',
    ...card
}: Spanned & ComponentProps<typeof MetricCard>) {
    return (
        <Cell span={span}>
            <MetricCard {...card} />
        </Cell>
    );
}

/**
 * A panel in the grid, for content that has no widget of its own. Takes the full row unless told
 * otherwise.
 */
export function PanelCell({
    span = 'full',
    ...panel
}: Spanned & ComponentProps<typeof Panel>) {
    return (
        <Cell span={span}>
            <Panel {...panel} />
        </Cell>
    );
}

/**
 * A trend chart in a panel. Charts with more than one series get a legend, and a badge points out
 * when every point beats or misses the benchmark lines. Takes half the row unless told otherwise.
 */
export function ChartPanel({
    span = '1/2',
    title,
    metric,
    description,
    actions,
    chart,
    legend = chart.series.length > 1,
    children,
}: Spanned & {
    title: ReactNode;
    /** Explains on hover how the charted metric is calculated. */
    metric?: ComponentProps<typeof MetricLabel>['metric'];
    description?: ReactNode;
    actions?: ReactNode;
    legend?: boolean;
    chart: TimeSeriesChartProps;
    /** Shown under the chart. */
    children?: ReactNode;
}) {
    return (
        <PanelCell
            span={span}
            title={
                metric ? (
                    <MetricLabel metric={metric}>{title}</MetricLabel>
                ) : (
                    title
                )
            }
            description={description}
            actions={
                <div className="flex flex-wrap items-center justify-end gap-1.5">
                    <BenchmarkNote {...chart} />
                    {actions}
                </div>
            }
        >
            {legend && (
                <Legend
                    items={chart.series.map(({ label, color }) => ({
                        label,
                        color,
                    }))}
                />
            )}
            <TimeSeriesChart {...chart} />
            {children}
        </PanelCell>
    );
}

export type MetricListItem<Key extends string = string> = {
    key: Key;
    label: string;
    hint?: string;
    metric: Metric;
    /** The metric whose icon sits before the label. */
    icon?: MetricKey;
    /** Draws the metric over time next to it. */
    series?: (number | null)[];
};

/**
 * Secondary metrics as dense rows. The rows flow into two or three columns when the panel is wide
 * enough, whatever span it gets. Pass `onSelect` to make the rows pickable. Takes the full row
 * unless told otherwise.
 */
export function MetricList<Key extends string>({
    span = 'full',
    title,
    description,
    actions,
    items,
    selected,
    onSelect,
}: Spanned & {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    items: MetricListItem<Key>[];
    selected?: Key;
    onSelect?: (key: Key) => void;
}) {
    return (
        <PanelCell
            span={span}
            title={title}
            description={description}
            actions={actions}
            bodyClassName="@container px-5 pb-2 pt-1"
        >
            {/* A rule between rows, but not above the first row, however many columns there are. Rows may
                shrink below their content, so a long label can't push the values out of the panel. */}
            <div className="grid grid-cols-1 gap-x-10 @xl:grid-cols-2 @4xl:grid-cols-3 [&>*]:min-w-0 [&>*]:border-t [&>*]:border-line [&>*:first-child]:border-t-0 @xl:[&>*:nth-child(2)]:border-t-0 @4xl:[&>*:nth-child(3)]:border-t-0">
                {items.map((item) => (
                    <div key={item.key}>
                        <MetricRow
                            label={item.label}
                            hint={item.hint}
                            metric={item.metric}
                            icon={item.icon}
                            series={item.series}
                            selected={item.key === selected}
                            onSelect={
                                onSelect ? () => onSelect(item.key) : undefined
                            }
                        />
                    </div>
                ))}
            </div>
        </PanelCell>
    );
}

/**
 * A placeholder the size of a widget, shown while its data loads.
 */
export function SkeletonCell({
    span = '1/4',
    className,
}: Spanned & { className?: string }) {
    return (
        <Cell span={span}>
            <div
                className={cn(
                    'h-32 animate-pulse rounded-xl border border-line bg-surface',
                    className,
                )}
            />
        </Cell>
    );
}

export function Legend({
    items,
}: {
    items: { label: string; color: string }[];
}) {
    return (
        <div className="-mt-1 mb-3 flex gap-4 text-xs text-ink-secondary">
            {items.map((item) => (
                <span key={item.label} className="flex items-center gap-1.5">
                    <span
                        className="size-2 rounded-sm"
                        style={{ background: item.color }}
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}
