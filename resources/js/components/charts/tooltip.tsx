import type { ReactNode } from 'react';

type TooltipRow = {
    label: string;
    value: string;
    color?: string;
};

export function ChartTooltip({
    x,
    y,
    width,
    title,
    rows,
}: {
    x: number;
    y: number;
    width: number;
    title: ReactNode;
    rows: TooltipRow[];
}) {
    const flip = x > width - 180;

    return (
        <div
            className="pointer-events-none absolute z-10 min-w-36 rounded-lg border border-line bg-surface-raised px-3 py-2 text-xs shadow-lg shadow-black/5"
            style={{
                left: flip ? undefined : x + 12,
                right: flip ? width - x + 12 : undefined,
                top: Math.max(0, y - 8),
            }}
        >
            <div className="mb-1 font-medium text-ink">{title}</div>
            {rows.map((row) => (
                <div
                    key={row.label}
                    className="flex items-center justify-between gap-4 text-ink-secondary"
                >
                    <span className="flex items-center gap-1.5">
                        {row.color && (
                            <span
                                className="size-2 rounded-full"
                                style={{ background: row.color }}
                            />
                        )}
                        {row.label}
                    </span>
                    <span className="tabular font-medium text-ink">
                        {row.value}
                    </span>
                </div>
            ))}
        </div>
    );
}
