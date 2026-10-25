import { useEffect, useRef, useState } from 'react';
import { Select } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import {
    isCustomPeriod,
    PERIOD_OPTIONS,
    periodLabel,
    toDateInput,
} from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { buttonClass } from '@/components/ui/button';

const CUSTOM = 'custom';
const ALL = 'all';

/**
 * The rolling presets and all of the cached history, plus a custom range of whole days.
 * Inline, it stacks the range form under the select, for use inside a panel.
 */
export function PeriodPicker({
    value,
    onChange,
    inline = false,
}: {
    value: PeriodFilters;
    onChange: (period: PeriodFilters) => void;
    inline?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const custom = isCustomPeriod(value);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !ref.current?.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    return (
        <div
            ref={ref}
            className={cn(
                'relative flex gap-2',
                inline ? 'flex-col' : 'items-center',
            )}
        >
            {custom && !(inline && open) && (
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className={cn(
                        'text-xs font-medium text-ink-secondary hover:text-ink',
                        inline && 'self-start',
                    )}
                >
                    {periodLabel(value)}
                </button>
            )}
            <Select
                label="Reporting period"
                className={cn(inline && 'order-first w-full')}
                value={custom ? CUSTOM : value.all ? ALL : String(value.days)}
                onChange={(next) => {
                    if (next === CUSTOM) {
                        setOpen(true);
                    } else {
                        setOpen(false);
                        onChange({
                            days: next === ALL ? value.days : Number(next),
                            from: null,
                            to: null,
                            all: next === ALL,
                        });
                    }
                }}
                options={[
                    ...PERIOD_OPTIONS.map((option) => ({
                        value: String(option.value),
                        label: option.label,
                    })),
                    { value: ALL, label: 'All time' },
                    { value: CUSTOM, label: 'Custom range…' },
                ]}
            />
            {open && (
                <RangeForm
                    inline={inline}
                    value={value}
                    onClose={() => setOpen(false)}
                    onApply={(from, to) => {
                        setOpen(false);
                        onChange({ days: value.days, from, to, all: false });
                    }}
                />
            )}
        </div>
    );
}

function RangeForm({
    value,
    onApply,
    onClose,
    inline,
}: {
    inline: boolean;
    value: PeriodFilters;
    onApply: (from: string, to: string) => void;
    onClose: () => void;
}) {
    const today = toDateInput(new Date());
    const [from, setFrom] = useState(
        value.from ??
            toDateInput(new Date(Date.now() - (value.days - 1) * 86_400_000)),
    );
    const [to, setTo] = useState(value.to ?? today);
    const valid = from !== '' && to !== '' && from <= to;

    const input =
        'h-8 w-full rounded-lg border border-line bg-surface px-2 text-xs text-ink focus:border-accent focus:ring-2 focus:ring-accent-soft focus:outline-none';

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();

                if (valid) {
                    onApply(from, to);
                }
            }}
            className={cn(
                'flex flex-col gap-3',
                !inline &&
                    'absolute top-full right-0 z-20 mt-2 rounded-xl border border-line bg-surface-raised p-4 shadow-lg',
            )}
        >
            <div className="flex items-end gap-2">
                <label className="flex min-w-0 flex-1 flex-col gap-1 text-xs text-ink-muted">
                    From
                    <input
                        type="date"
                        value={from}
                        max={to || today}
                        onChange={(event) => setFrom(event.target.value)}
                        className={input}
                        autoFocus
                    />
                </label>
                <label className="flex min-w-0 flex-1 flex-col gap-1 text-xs text-ink-muted">
                    To
                    <input
                        type="date"
                        value={to}
                        min={from}
                        max={today}
                        onChange={(event) => setTo(event.target.value)}
                        className={input}
                    />
                </label>
            </div>
            <div className="flex justify-end gap-2">
                <button
                    type="button"
                    onClick={onClose}
                    className={buttonClass({ variant: 'ghost' })}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!valid}
                    className={buttonClass({ variant: 'primary', size: 'sm' })}
                >
                    Apply
                </button>
            </div>
        </form>
    );
}
