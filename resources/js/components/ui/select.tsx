import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { CheckIcon, ChevronDownIcon } from '@/components/icons';
import { LAYER } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const GAP = 4;
const MARGIN = 12;

export type SelectOption<T extends string> = {
    value: T;
    label: string;
    /** Shown before the label, e.g. an avatar. */
    icon?: ReactNode;
};

/**
 * Lowercase, with underscores and slashes read as spaces, so "new york" finds "America/New_York".
 */
const searchable = (text: string) => text.toLowerCase().replace(/[_/]/g, ' ');

/**
 * The options whose label or value contains every word of the query, in their original order.
 */
export function filterOptions<T extends string>(
    options: SelectOption<T>[],
    query: string,
): SelectOption<T>[] {
    const terms = searchable(query).split(' ').filter(Boolean);

    if (terms.length === 0) {
        return options;
    }

    return options.filter((option) => {
        const text = searchable(`${option.label} ${option.value}`);

        return terms.every((term) => text.includes(term));
    });
}

/**
 * A styled stand-in for the native select, with a trigger that matches the secondary buttons.
 * Focus stays on the trigger while the list is open, so the arrow keys, Home, End, Page Up,
 * Page Down, Enter, Escape and typing a letter all work from there. The
 * list renders in a portal so clipping containers don't cut it off, and flips above when
 * there is no room below.
 *
 * With `search`, the list opens with a field that filters it by every word typed, for lists too
 * long to scroll through. Focus moves to the field, and the arrow keys, Page Up, Page Down, Enter
 * and Escape work from there.
 */
export function Select<T extends string>({
    value,
    onChange,
    options,
    label,
    className,
    search = false,
}: {
    value: T;
    onChange: (value: T) => void;
    options: SelectOption<T>[];
    label: string;
    className?: string;
    search?: boolean;
}) {
    const id = useId();
    const trigger = useRef<HTMLButtonElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const field = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [query, setQuery] = useState('');
    const [position, setPosition] = useState<{
        top?: number;
        bottom?: number;
        left: number;
        minWidth: number;
    } | null>(null);

    const shown = filterOptions(options, query);
    const selectedIndex = shown.findIndex((option) => option.value === value);
    const selected = options.find((option) => option.value === value);

    const show = (index: number = Math.max(0, selectedIndex)) => {
        setActive(index);
        setOpen(true);
    };

    const hide = () => {
        setOpen(false);
        setPosition(null);
        setQuery('');

        // The field had focus, so hand it back to the trigger rather than lose it to the page.
        if (search && document.activeElement === field.current) {
            trigger.current?.focus();
        }
    };

    const choose = (index: number) => {
        const option = shown[index];

        hide();

        if (option && option.value !== value) {
            onChange(option.value);
        }
    };

    const place = () => {
        if (!trigger.current || !panel.current) {
            return;
        }

        const target = trigger.current.getBoundingClientRect();
        const { height } = panel.current.getBoundingClientRect();
        const width = Math.max(
            panel.current.getBoundingClientRect().width,
            target.width,
        );
        const fitsBelow =
            target.bottom + GAP + height <= window.innerHeight - MARGIN;
        const fitsAbove = target.top - GAP - height >= MARGIN;

        setPosition({
            // Above the trigger, anchor the bottom edge, so a list that shrinks while searching stays
            // next to the trigger.
            ...(fitsBelow || !fitsAbove
                ? {
                      top: fitsBelow
                          ? target.bottom + GAP
                          : Math.max(MARGIN, target.top - GAP - height),
                  }
                : { bottom: window.innerHeight - target.top + GAP }),
            // Line up with the trigger's left edge, or its right edge when that runs off the screen.
            left: Math.max(
                MARGIN,
                target.left + width <= window.innerWidth - MARGIN
                    ? target.left
                    : target.right - width,
            ),
            minWidth: target.width,
        });
    };

    useLayoutEffect(() => {
        if (open) {
            place();
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const outside = (event: Event) => {
            const node = event.target as Node;

            if (
                !trigger.current?.contains(node) &&
                !panel.current?.contains(node)
            ) {
                hide();
            }
        };

        // Focusing the search field opens a phone's keyboard, which resizes the window or scrolls the
        // page, so a searchable list follows its trigger instead of closing.
        const moved = (event: Event) => {
            // Scrolling the list itself moves nothing.
            if (
                event.target instanceof Node &&
                panel.current?.contains(event.target)
            ) {
                return;
            }

            if (search) {
                place();
            } else {
                hide();
            }
        };

        window.addEventListener('mousedown', outside);
        window.addEventListener('scroll', moved, true);
        window.addEventListener('resize', moved);

        return () => {
            window.removeEventListener('mousedown', outside);
            window.removeEventListener('scroll', moved, true);
            window.removeEventListener('resize', moved);
        };
    }, [open]);

    const placed = position !== null;

    useEffect(() => {
        if (open && search && placed) {
            field.current?.focus();
        }
    }, [open, search, placed]);

    useEffect(() => {
        if (open) {
            panel.current
                ?.querySelector(`[data-index="${active}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [open, active]);

    const onKeyDown = (event: KeyboardEvent) => {
        const last = shown.length - 1;

        if (!open) {
            if (
                ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(
                    event.key,
                )
            ) {
                event.preventDefault();
                show(
                    event.key === 'Home'
                        ? 0
                        : event.key === 'End'
                          ? options.length - 1
                          : undefined,
                );
            }

            return;
        }

        if (event.altKey && event.key === 'ArrowUp') {
            choose(active);
            event.preventDefault();

            return;
        }

        // Typing, spaces, Home and End belong to the search field.
        if (
            search &&
            (event.key.length === 1 || ['Home', 'End'].includes(event.key))
        ) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
                setActive(Math.min(last, active + 1));
                break;
            case 'ArrowUp':
                setActive(Math.max(0, active - 1));
                break;
            case 'Home':
            case 'PageUp':
                setActive(0);
                break;
            case 'End':
            case 'PageDown':
                setActive(last);
                break;
            case 'Enter':
            case ' ':
                choose(active);
                break;
            case 'Escape':
                // Handled here, so a popover the select sits in stays open.
                hide();
                break;
            case 'Tab':
                hide();

                return;
            default: {
                if (event.key.length !== 1) {
                    return;
                }

                const letter = event.key.toLowerCase();
                const next = [
                    ...shown.slice(active + 1),
                    ...shown.slice(0, active + 1),
                ].find((option) =>
                    option.label.toLowerCase().startsWith(letter),
                );

                if (next) {
                    setActive(shown.indexOf(next));
                }
            }
        }

        event.preventDefault();
    };

    return (
        <>
            <button
                ref={trigger}
                type="button"
                role="combobox"
                aria-label={label}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? id : undefined}
                aria-activedescendant={open ? `${id}-${active}` : undefined}
                onClick={() => (open ? hide() : show())}
                onKeyDown={onKeyDown}
                className={cn(
                    'inline-flex h-8 min-w-0 cursor-pointer items-center gap-1.5 rounded-lg border border-line-strong bg-surface pr-2 pl-3 text-left text-xs font-semibold text-ink transition hover:border-ink-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent aria-expanded:border-ink-muted',
                    className,
                )}
            >
                {selected?.icon}
                <span className="truncate">{selected?.label}</span>
                <ChevronDownIcon
                    width={13}
                    height={13}
                    className={cn(
                        'ml-auto shrink-0 text-ink-muted transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open &&
                createPortal(
                    <div
                        ref={panel}
                        {...LAYER}
                        className={cn(
                            'fixed z-50 flex flex-col rounded-xl border border-line bg-surface-raised text-xs shadow-lg shadow-black/10',
                            // A fixed width, so filtering doesn't make the panel jump sideways.
                            search ? 'max-h-80 w-72' : 'max-h-72',
                        )}
                        style={
                            position ?? {
                                top: 0,
                                left: 0,
                                visibility: 'hidden',
                            }
                        }
                    >
                        {search && (
                            <input
                                ref={field}
                                type="search"
                                role="combobox"
                                aria-label={`Search ${label.toLowerCase()}`}
                                aria-autocomplete="list"
                                aria-expanded
                                aria-controls={id}
                                aria-activedescendant={
                                    shown.length > 0
                                        ? `${id}-${active}`
                                        : undefined
                                }
                                placeholder="Search…"
                                value={query}
                                onChange={(event) => {
                                    setQuery(event.target.value);
                                    setActive(0);
                                }}
                                onKeyDown={onKeyDown}
                                className="m-1 mb-0 h-8 shrink-0 rounded-lg border border-line bg-surface px-2.5 text-xs text-ink outline-none placeholder:text-ink-muted focus:border-ink-muted"
                            />
                        )}
                        <ul
                            id={id}
                            role="listbox"
                            aria-label={label}
                            className="min-h-0 flex-1 overflow-y-auto p-1"
                        >
                            {shown.length === 0 && (
                                <li className="px-2.5 py-1.5 text-ink-muted">
                                    No matches
                                </li>
                            )}
                            {shown.map((option, index) => (
                            <li
                                key={option.value}
                                id={`${id}-${index}`}
                                data-index={index}
                                role="option"
                                aria-selected={option.value === value}
                                onMouseDown={(event) => event.preventDefault()}
                                onMouseMove={() => setActive(index)}
                                onClick={() => choose(index)}
                                className={cn(
                                    'flex cursor-default items-center gap-2 rounded-lg py-1.5 pr-2.5 pl-2 whitespace-nowrap text-ink-secondary',
                                    index === active && 'bg-line/60 text-ink',
                                    option.value === value && 'font-medium text-ink',
                                )}
                            >
                                <CheckIcon
                                    width={13}
                                    height={13}
                                    className={cn(
                                        'shrink-0',
                                        option.value !== value && 'invisible',
                                    )}
                                />
                                {option.icon}
                                {option.label}
                            </li>
                        ))}
                        </ul>
                    </div>,
                    document.body,
                )}
        </>
    );
}
