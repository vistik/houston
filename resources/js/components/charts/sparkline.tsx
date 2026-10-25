import { useElementSize } from '@/hooks/use-element-width';
import { cn } from '@/lib/utils';

/**
 * A minimal area chart. Pass a pixel height, or `fill` to stretch to the parent's height.
 */
export function Sparkline({
    values,
    color = 'var(--series-1)',
    height = 36,
    fill = false,
    bars = false,
    className,
}: {
    values: number[];
    color?: string;
    height?: number;
    fill?: boolean;
    bars?: boolean;
    className?: string;
}) {
    const [ref, size] = useElementSize<HTMLDivElement>();
    const width = size.width;
    const chartHeight = fill ? size.height : height;
    const max = Math.max(1, ...values);
    const step = values.length > 1 ? width / (values.length - 1) : 0;
    const points = values.map(
        (value, index) =>
            [
                index * step,
                chartHeight - 2 - (value / max) * (chartHeight - 4),
            ] as const,
    );
    const line = points
        .map(([x, y], i) => `${i ? 'L' : 'M'}${x},${y}`)
        .join(' ');

    return (
        <div
            ref={ref}
            className={cn('w-full', fill && 'h-full', className)}
            style={fill ? undefined : { height }}
        >
            {bars && width > 0 && chartHeight > 0 && (
                <svg width={width} height={chartHeight} aria-hidden>
                    {values.map((value, index) => {
                        const slot = width / values.length;
                        const barWidth = Math.max(
                            2,
                            slot - Math.min(8, slot * 0.3),
                        );
                        const barHeight =
                            value > 0
                                ? Math.max(3, (value / max) * chartHeight)
                                : 0;

                        return (
                            <rect
                                key={index}
                                x={index * slot + (slot - barWidth) / 2}
                                y={chartHeight - barHeight}
                                width={barWidth}
                                height={barHeight}
                                rx={Math.min(4, barWidth / 2)}
                                fill={color}
                            />
                        );
                    })}
                </svg>
            )}
            {!bars && width > 0 && chartHeight > 0 && values.length > 1 && (
                <svg width={width} height={chartHeight} aria-hidden>
                    <path
                        d={`${line} L${width},${chartHeight} L0,${chartHeight} Z`}
                        fill={color}
                        opacity={0.12}
                    />
                    <path
                        d={line}
                        fill="none"
                        stroke={color}
                        strokeWidth={fill ? 2.5 : 1.5}
                        strokeLinejoin="round"
                    />
                </svg>
            )}
        </div>
    );
}
