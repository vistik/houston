import { router } from '@inertiajs/react';
import { use, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { useElementWidth } from '@/hooks/use-element-width';
import { TIER_LABELS } from '@/lib/benchmarks';
import { formatDate } from '@/lib/format';
import { eventPositions, TimelineEvents } from '@/lib/timeline-events';
import type { Thresholds, TimelineEvent } from '@/types';
import { niceDurationScale, niceScale } from './scale';
import { ChartTooltip } from './tooltip';

export type Series = {
    key: string;
    label: string;
    color: string;
    values: (number | null)[];
};

export type TimeSeriesChartProps = {
    labels: string[];
    tooltipLabels?: string[];
    series: Series[];
    kind: 'bar' | 'line';
    format: (value: number | null) => string;
    durationAxis?: boolean;
    maxValue?: number;
    height?: number;
    /** Draws a line per benchmark tier that falls within the axis. */
    benchmark?: Thresholds;
    /**
     * The day each bucket starts, as YYYY-MM-DD, for charts over time. Marks the page's timeline
     * events on the chart.
     */
    dates?: string[];
};

export const TIER_LINES = [
    { tier: 'elite', color: 'var(--good)', opacity: 1 },
    { tier: 'good', color: 'var(--good)', opacity: 0.5 },
    { tier: 'medium', color: 'var(--warn)', opacity: 1 },
] as const;

const PADDING = { top: 12, right: 8, bottom: 24, left: 44 };

/**
 * Bars or lines over evenly spaced buckets, with a shared hover tooltip per bucket.
 */
export function TimeSeriesChart({
    labels,
    tooltipLabels = labels,
    series,
    kind,
    format,
    durationAxis = false,
    maxValue,
    height = 180,
    benchmark,
    dates,
}: TimeSeriesChartProps) {
    const [ref, width] = useElementWidth<HTMLDivElement>();
    const [hovered, setHovered] = useState<number | null>(null);
    const [hoveredEvent, setHoveredEvent] = useState<{
        event: TimelineEvent;
        x: number;
    } | null>(null);
    const timeline = use(TimelineEvents);

    const scale = axisScale(series, durationAxis, maxValue);
    const yMax = scale.max;
    const ticks = scale.ticks;

    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const plotHeight = height - PADDING.top - PADDING.bottom;
    const count = labels.length;
    const band = count > 0 ? plotWidth / count : 0;
    const y = (value: number) =>
        PADDING.top + plotHeight - (value / yMax) * plotHeight;
    const xCenter = (index: number) => PADDING.left + band * index + band / 2;
    const markers =
        timeline && dates
            ? eventPositions(timeline.events, dates, timeline.bucketDays).map(
                  ({ event, at }) => ({
                      event,
                      x: PADDING.left + band * at,
                      focused: event.id === timeline.focused?.id,
                  }),
              )
            : [];
    const split = markers.find((marker) => marker.focused);

    const labelEvery = Math.max(
        1,
        Math.ceil(count / Math.max(1, plotWidth / 64)),
    );
    const hasData = hasValues(series);

    return (
        <div ref={ref} className="relative w-full" style={{ height }}>
            {width > 0 && (
                <svg
                    width={width}
                    height={height}
                    role="img"
                    className="overflow-visible"
                    onMouseLeave={() => setHovered(null)}
                >
                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={PADDING.left}
                                x2={width - PADDING.right}
                                y1={y(tick)}
                                y2={y(tick)}
                                stroke={
                                    tick === 0
                                        ? 'var(--line-strong)'
                                        : 'var(--grid)'
                                }
                                strokeWidth={1}
                            />
                            <text
                                x={PADDING.left - 8}
                                y={y(tick)}
                                dy="0.32em"
                                textAnchor="end"
                                className="tabular fill-ink-muted text-[10px]"
                            >
                                {format(tick)}
                            </text>
                        </g>
                    ))}

                    {labels.map((label, index) =>
                        index % labelEvery === 0 ? (
                            <text
                                key={label + index}
                                x={xCenter(index)}
                                y={height - 6}
                                textAnchor="middle"
                                className="fill-ink-muted text-[10px]"
                            >
                                {label}
                            </text>
                        ) : null,
                    )}

                    {split && (
                        <BeforeAndAfter
                            x={split.x}
                            left={PADDING.left}
                            right={width - PADDING.right}
                            top={PADDING.top}
                            height={plotHeight}
                        />
                    )}

                    {hovered !== null && (
                        <rect
                            x={PADDING.left + band * hovered}
                            y={PADDING.top}
                            width={band}
                            height={plotHeight}
                            className="fill-ink/[0.04]"
                        />
                    )}

                    {benchmark &&
                        TIER_LINES.map(({ tier, color, opacity }) =>
                            benchmark[tier] > 0 && benchmark[tier] <= yMax ? (
                                <g key={tier} opacity={opacity}>
                                    <line
                                        x1={PADDING.left}
                                        x2={width - PADDING.right}
                                        y1={y(benchmark[tier])}
                                        y2={y(benchmark[tier])}
                                        stroke={color}
                                        strokeWidth={1}
                                        strokeDasharray="4 3"
                                    />
                                    <text
                                        x={width - PADDING.right}
                                        y={y(benchmark[tier]) - 3}
                                        textAnchor="end"
                                        fill={color}
                                        className="text-[10px]"
                                    >
                                        {TIER_LABELS[tier]}
                                    </text>
                                </g>
                            ) : null,
                        )}

                    {kind === 'bar' && (
                        <Bars
                            series={series}
                            band={band}
                            y={y}
                            baseline={y(0)}
                        />
                    )}
                    {kind === 'line' && (
                        <Lines
                            series={series}
                            xCenter={xCenter}
                            y={y}
                            hovered={hovered}
                        />
                    )}

                    {labels.map((label, index) => (
                        <rect
                            key={`hit-${label}-${index}`}
                            x={PADDING.left + band * index}
                            y={PADDING.top}
                            width={band}
                            height={plotHeight}
                            fill="transparent"
                            onMouseEnter={() => setHovered(index)}
                        />
                    ))}

                    {timeline &&
                        markers.map(({ event, x, focused }) => (
                            <EventMarker
                                key={event.id}
                                event={event}
                                x={x}
                                focused={focused}
                                top={PADDING.top}
                                height={plotHeight}
                                href={timeline.href(event)}
                                onHover={(x) => {
                                    setHovered(null);
                                    setHoveredEvent(
                                        x === null ? null : { event, x },
                                    );
                                }}
                            />
                        ))}
                </svg>
            )}

            {!hasData && width > 0 && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center pl-10 text-xs text-ink-muted">
                    No data in this period
                </div>
            )}

            {hoveredEvent && (
                <ChartTooltip
                    x={hoveredEvent.x}
                    y={PADDING.top}
                    width={width}
                    title={
                        <>
                            {hoveredEvent.event.title}
                            <div className="font-normal text-ink-secondary">
                                {formatDate(hoveredEvent.event.date, true)} ·
                                click to compare before and after
                            </div>
                        </>
                    }
                    rows={[]}
                />
            )}

            {hovered !== null && (
                <ChartTooltip
                    x={xCenter(hovered)}
                    y={PADDING.top}
                    width={width}
                    title={tooltipLabels[hovered]}
                    rows={series.map((entry) => ({
                        label: entry.label,
                        value: format(entry.values[hovered]),
                        color: entry.color,
                    }))}
                />
            )}
        </div>
    );
}

/**
 * Names the tier every bucket lands in when all benchmark lines sit above the chart's axis, so a
 * chart without lines still says how it rates. Takes the same props as the chart it accompanies.
 */
export function BenchmarkNote({
    series,
    format,
    durationAxis = false,
    maxValue,
    benchmark,
    className,
}: TimeSeriesChartProps & { className?: string }) {
    const tier =
        benchmark && hasValues(series)
            ? offChartTier(
                  benchmark,
                  axisScale(series, durationAxis, maxValue).max,
              )
            : null;

    if (!benchmark || !tier) {
        return null;
    }

    return (
        <Badge tone={tier === 'elite' ? 'good' : 'bad'} className={className}>
            All {TIER_LABELS[tier]} ·{' '}
            {tier === 'elite'
                ? `≤ ${format(benchmark.elite)}`
                : `< ${format(benchmark.medium)}`}
        </Badge>
    );
}

/**
 * The tier every value on an axis up to `max` lands in when all benchmark lines sit above it: elite
 * when lower is better, low when higher is. Null when any line is drawn.
 */
export function offChartTier(
    benchmark: Thresholds,
    max: number,
): 'elite' | 'low' | null {
    const thresholds = TIER_LINES.map(({ tier }) => benchmark[tier]).filter(
        (threshold) => threshold > 0,
    );

    if (
        thresholds.length === 0 ||
        thresholds.some((threshold) => threshold <= max)
    ) {
        return null;
    }

    return benchmark.direction === 'lower' ? 'elite' : 'low';
}

function axisScale(
    series: Series[],
    durationAxis: boolean,
    maxValue: number | undefined,
): { max: number; ticks: number[] } {
    const dataMax = Math.max(
        0,
        ...series.flatMap((entry) => entry.values.filter((v) => v !== null)),
    );

    return durationAxis
        ? niceDurationScale(dataMax)
        : niceScale(maxValue ?? dataMax);
}

function hasValues(series: Series[]): boolean {
    return series.some((entry) =>
        entry.values.some((value) => value !== null && value > 0),
    );
}

/**
 * Shades the time before the event a report compares, and labels both sides.
 */
function BeforeAndAfter({
    x,
    left,
    right,
    top,
    height,
}: {
    x: number;
    left: number;
    right: number;
    top: number;
    height: number;
}) {
    return (
        <g className="pointer-events-none">
            <rect
                x={left}
                y={top}
                width={Math.max(0, x - left)}
                height={height}
                className="fill-ink/[0.035]"
            />
            <text
                x={x - 6}
                y={top + 10}
                textAnchor="end"
                className="fill-ink-muted text-[10px] font-medium tracking-wide uppercase"
            >
                Before
            </text>
            <text
                x={Math.min(x + 6, right)}
                y={top + 10}
                className="fill-accent text-[10px] font-medium tracking-wide uppercase"
            >
                After
            </text>
        </g>
    );
}

/**
 * A timeline event as a dashed vertical line from the day it happened, which opens the report
 * comparing the time since with the time before. The event the report compares is drawn solid.
 */
function EventMarker({
    event,
    x,
    focused,
    top,
    height,
    href,
    onHover,
}: {
    event: TimelineEvent;
    x: number;
    focused: boolean;
    top: number;
    height: number;
    href: string;
    onHover: (x: number | null) => void;
}) {
    const color = focused ? 'var(--accent)' : 'var(--ink-secondary)';

    return (
        <a
            href={href}
            aria-label={`${event.title}, ${formatDate(event.date, true)}: compare before and after`}
            className="group cursor-pointer focus:outline-none"
            onClick={(clicked) => {
                if (clicked.metaKey || clicked.ctrlKey || clicked.shiftKey) {
                    return;
                }

                clicked.preventDefault();
                router.visit(href);
            }}
            onMouseEnter={() => onHover(x)}
            onMouseLeave={() => onHover(null)}
            onFocus={() => onHover(x)}
            onBlur={() => onHover(null)}
        >
            <line
                x1={x}
                x2={x}
                y1={top}
                y2={top + height}
                stroke={color}
                strokeWidth={focused ? 1.5 : 1.25}
                strokeDasharray={focused ? undefined : '3 3'}
                className={
                    focused
                        ? undefined
                        : 'opacity-60 transition group-hover:opacity-100 group-focus-visible:opacity-100'
                }
            />
            <path
                d={`M${x},${top - 6} l4,4 l-4,4 l-4,-4 Z`}
                fill={color}
                className="opacity-80 transition group-hover:opacity-100"
            />
            <rect
                x={x - 6}
                y={top - 8}
                width={12}
                height={height + 8}
                fill="transparent"
            />
        </a>
    );
}

function Bars({
    series,
    band,
    y,
    baseline,
}: {
    series: Series[];
    band: number;
    y: (value: number) => number;
    baseline: number;
}) {
    const groupWidth = Math.min(
        band * 0.7,
        18 * series.length + 2 * (series.length - 1),
    );
    const barWidth = Math.max(
        1,
        (groupWidth - 2 * (series.length - 1)) / series.length,
    );
    const radius = Math.min(4, barWidth / 2);

    return (
        <g>
            {series.map((entry, seriesIndex) =>
                entry.values.map((value, index) => {
                    if (value === null || value <= 0) {
                        return null;
                    }

                    const x =
                        PADDING.left +
                        band * index +
                        (band - groupWidth) / 2 +
                        seriesIndex * (barWidth + 2);
                    const top = y(value);
                    const barHeight = Math.max(1, baseline - top);
                    const r = Math.min(radius, barHeight);

                    return (
                        <path
                            key={`${entry.key}-${index}`}
                            d={`M${x},${baseline} V${top + r} Q${x},${top} ${x + r},${top} H${x + barWidth - r} Q${x + barWidth},${top} ${x + barWidth},${top + r} V${baseline} Z`}
                            fill={entry.color}
                        />
                    );
                }),
            )}
        </g>
    );
}

function Lines({
    series,
    xCenter,
    y,
    hovered,
}: {
    series: Series[];
    xCenter: (index: number) => number;
    y: (value: number) => number;
    hovered: number | null;
}) {
    return (
        <g>
            {series.map((entry) => {
                const segments: string[] = [];
                let current = '';

                entry.values.forEach((value, index) => {
                    if (value === null) {
                        if (current) {
                            segments.push(current);
                        }
                        current = '';

                        return;
                    }

                    current += `${current ? 'L' : 'M'}${xCenter(index)},${y(value)} `;
                });

                if (current) {
                    segments.push(current);
                }

                return (
                    <g key={entry.key}>
                        {segments.map((path, index) => (
                            <path
                                key={index}
                                d={path}
                                fill="none"
                                stroke={entry.color}
                                strokeWidth={2}
                                strokeLinejoin="round"
                                strokeLinecap="round"
                            />
                        ))}
                        {entry.values.map((value, index) =>
                            value === null ? null : (
                                <circle
                                    key={index}
                                    cx={xCenter(index)}
                                    cy={y(value)}
                                    r={hovered === index ? 4.5 : 2.5}
                                    fill={entry.color}
                                    stroke="var(--surface)"
                                    strokeWidth={2}
                                />
                            ),
                        )}
                    </g>
                );
            })}
        </g>
    );
}
