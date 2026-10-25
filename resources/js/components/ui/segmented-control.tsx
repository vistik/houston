import { Tooltip } from '@/components/ui/hover-card';
import { cn } from '@/lib/utils';

export function SegmentedControl<T extends string | number>({
    options,
    value,
    onChange,
    label,
    stretch = false,
}: {
    options: { value: T; label: string; disabled?: boolean; title?: string }[];
    value: T;
    onChange: (value: T) => void;
    label: string;
    /** Fill the container's width, sharing it evenly between the options. */
    stretch?: boolean;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={cn(
                'rounded-lg border border-line bg-surface p-0.5',
                stretch ? 'flex' : 'inline-flex self-start',
            )}
        >
            {options.map((option) => (
                <Tooltip
                    key={option.value}
                    content={option.title}
                    className={cn(
                        stretch && 'flex-1',
                        option.disabled && 'cursor-not-allowed',
                    )}
                >
                    <button
                        type="button"
                        role="radio"
                        aria-checked={option.value === value}
                        disabled={option.disabled}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'rounded-md px-2.5 py-1 text-xs font-medium text-ink-secondary transition-colors outline-none hover:text-ink focus-visible:ring-2 focus-visible:ring-accent disabled:pointer-events-none disabled:opacity-40',
                            stretch && 'w-full',
                            option.value === value &&
                                'bg-surface-raised text-ink shadow-sm ring-1 ring-line-strong',
                        )}
                    >
                        {option.label}
                    </button>
                </Tooltip>
            ))}
        </div>
    );
}
