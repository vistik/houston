import { useId } from 'react';
import type { ReactNode } from 'react';

/**
 * A checkbox with its label, and optionally a line describing it.
 */
export function Checkbox({
    checked,
    onChange,
    label,
    description,
    disabled = false,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: ReactNode;
    description?: string;
    disabled?: boolean;
}) {
    const id = useId();

    return (
        <div className="flex gap-2.5">
            <span className="flex h-lh items-center text-xs">
                <span className="group inline-grid size-4 grid-cols-1">
                    <input
                        id={id}
                        type="checkbox"
                        name={id}
                        checked={checked}
                        disabled={disabled}
                        onChange={(event) => onChange(event.target.checked)}
                        aria-describedby={
                            description ? `${id}-description` : undefined
                        }
                        className="col-start-1 row-start-1 appearance-none rounded-sm border border-line-strong bg-surface checked:border-accent checked:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-50 forced-colors:appearance-auto"
                    />
                    <svg
                        viewBox="0 0 14 14"
                        fill="none"
                        className="pointer-events-none col-start-1 row-start-1 size-7/8 self-center justify-self-center stroke-white"
                    >
                        <path
                            d="M3 8L6 11L11 3.5"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="group-not-has-checked:opacity-0"
                        />
                    </svg>
                </span>
            </span>
            <div className="text-xs">
                <label
                    htmlFor={id}
                    className="cursor-pointer font-medium text-ink select-none"
                >
                    {label}
                </label>
                {description && (
                    <p
                        id={`${id}-description`}
                        className="mt-0.5 text-ink-muted"
                    >
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
