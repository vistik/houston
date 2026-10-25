import { useElementWidth } from '@/hooks/use-element-width';
import type { HistoryKind } from '@/lib/metric-history';
import { cn } from '@/lib/utils';

const INSET = 4;

/**
 * A small chart of one metric across the trend buckets, for a hover card. The axis starts at zero,
 * buckets without a value leave a gap, and the latest bucket with one stands out.
 */
export function MiniTrend({
    values,
    kind,
    height = 44,
    className,
}: {
    values: (number | null)[];
    kind: HistoryKind;
    height?: number;
    className?: string;
}) {
    const [ref, width] = useElementWidth<HTMLDivElement>();
    const max = Math.max(
        0,
        ...values.filter((value): value is number => value !== null),
    );
    const latest = values.findLastIndex((value) => value !== null);
    const plotHeight = height - INSET * 2;
    const y = (value: number) =>
        INSET + plotHeight - (max > 0 ? (value / max) * plotHeight : 0);

    return (
        <div ref={ref} className={cn('w-full', className)} style={{ height }}>
            {width > 0 &&
                (kind === 'bar' ? (
                    <Bars
                        values={values}
                        width={width}
                        height={height}
                        max={max}
                        latest={latest}
                    />
                ) : (
                    <Line
                        values={values}
                        width={width}
                        height={height}
                        y={y}
                        latest={latest}
                    />
                ))}
        </div>
    );
}

function Bars({
    values,
    width,
    height,
    max,
    latest,
}: {
    values: (number | null)[];
    width: number;
    height: number;
    max: number;
    latest: number;
}) {
    const slot = width / values.length;
    // A 2px gap between bars, however many buckets there are.
    const barWidth = Math.max(1, slot - Math.min(2, slot * 0.3));
    const rounding = Math.min(2, barWidth / 2);

    return (
        <svg width={width} height={height} aria-hidden>
            <line
                x1={0}
                x2={width}
                y1={height - 0.5}
                y2={height - 0.5}
                stroke="var(--line-strong)"
            />
            {values.map((value, index) => {
                if (value === null || value <= 0 || max <= 0) {
                    return null;
                }

                const barHeight = Math.max(2, (value / max) * (height - 2));

                return (
                    <path
                        key={index}
                        d={roundedTop(
                            index * slot + (slot - barWidth) / 2,
                            height - 1,
                            barWidth,
                            barHeight,
                            rounding,
                        )}
                        fill="var(--series-1)"
                        opacity={index === latest ? 1 : 0.45}
                    />
                );
            })}
        </svg>
    );
}

function Line({
    values,
    width,
    height,
    y,
    latest,
}: {
    values: (number | null)[];
    width: number;
    height: number;
    y: (value: number) => number;
    latest: number;
}) {
    const step =
        values.length > 1 ? (width - INSET * 2) / (values.length - 1) : 0;
    const x = (index: number) => INSET + index * step;
    const baseline = height - INSET;

    // Runs of consecutive buckets with a value, so a bucket without one breaks the line.
    const runs: [number, number][][] = [];
    let run: [number, number][] = [];

    values.forEach((value, index) => {
        if (value === null) {
            if (run.length > 0) {
                runs.push(run);
            }

            run = [];

            return;
        }

        run.push([x(index), y(value)]);
    });

    if (run.length > 0) {
        runs.push(run);
    }

    const latestValue = latest >= 0 ? values[latest] : null;

    return (
        <svg width={width} height={height} aria-hidden>
            <line
                x1={0}
                x2={width}
                y1={baseline + 0.5}
                y2={baseline + 0.5}
                stroke="var(--line-strong)"
            />
            {runs.map((points, index) => {
                const line = points
                    .map(([px, py], i) => `${i ? 'L' : 'M'}${px},${py}`)
                    .join(' ');

                return points.length === 1 ? (
                    <circle
                        key={index}
                        cx={points[0][0]}
                        cy={points[0][1]}
                        r={1.5}
                        fill="var(--series-1)"
                    />
                ) : (
                    <g key={index}>
                        <path
                            d={`${line} L${points[points.length - 1][0]},${baseline} L${points[0][0]},${baseline} Z`}
                            fill="var(--series-1)"
                            opacity={0.12}
                        />
                        <path
                            d={line}
                            fill="none"
                            stroke="var(--series-1)"
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    </g>
                );
            })}
            {latestValue !== null && (
                <circle
                    cx={x(latest)}
                    cy={y(latestValue)}
                    r={3.5}
                    fill="var(--series-1)"
                    stroke="var(--surface-raised)"
                    strokeWidth={2}
                />
            )}
        </svg>
    );
}

/**
 * A bar standing on the baseline with its top corners rounded.
 */
function roundedTop(
    x: number,
    bottom: number,
    width: number,
    height: number,
    radius: number,
): string {
    const r = Math.min(radius, height);
    const top = bottom - height;

    return [
        `M${x},${bottom}`,
        `L${x},${top + r}`,
        `Q${x},${top} ${x + r},${top}`,
        `L${x + width - r},${top}`,
        `Q${x + width},${top} ${x + width},${top + r}`,
        `L${x + width},${bottom}`,
        'Z',
    ].join(' ');
}
