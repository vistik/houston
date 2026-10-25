import { useHttp } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent, Ref } from 'react';
import { LockIcon, StarIcon } from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import { INITIAL_FOCUS } from '@/components/ui/dialog';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { suggestions as suggestionsRoute } from '@/routes/repositories';

type RepositorySuggestion = {
    fullName: string;
    owner: string;
    name: string;
    avatarUrl: string | null;
    private: boolean;
    stars: number | null;
    description: string | null;
    watched: boolean;
};

type SuggestionsResponse = {
    suggestions: RepositorySuggestion[];
    complete: boolean;
};

/**
 * Waits for a pause in typing before asking GitHub, which keeps the search budget for real queries.
 */
const DEBOUNCE_MS = 200;

/**
 * A text input for `owner/name` that suggests repositories from GitHub as you type, following the
 * ARIA combobox pattern: focus stays in the input while the arrow keys move through the list, Enter
 * picks a suggestion and Escape closes the list. When GitHub has nothing to say, it is a plain input.
 */
export function RepositoryCombobox({
    id,
    name,
    inputRef,
    placeholder,
    initialFocus = false,
}: {
    id: string;
    name: string;
    inputRef?: Ref<HTMLInputElement>;
    placeholder?: string;
    /** Whether a dialog around it should focus it when it opens. */
    initialFocus?: boolean;
}) {
    const listId = useId();
    const list = useRef<HTMLUListElement>(null);
    const latestRequest = useRef(0);
    const [value, setValue] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const [result, setResult] = useState<
        (SuggestionsResponse & { query: string }) | null
    >(null);

    const http = useHttp<{ q: string }, SuggestionsResponse>({ q: '' });
    const query = http.data.q;

    useEffect(() => {
        if (!open || result?.query === query) {
            return;
        }

        const timer = window.setTimeout(
            () => {
                // A newer request answers for what is typed now, so the older one is not needed.
                http.cancel();
                const request = ++latestRequest.current;
                const settle = (response: SuggestionsResponse | undefined) => {
                    if (request === latestRequest.current) {
                        setResult({
                            suggestions: response?.suggestions ?? [],
                            complete: response?.complete ?? false,
                            query,
                        });
                        setActive(-1);
                    }
                };

                http.get(suggestionsRoute.url()).then(settle, () =>
                    settle(undefined),
                );
            },
            query === '' ? 0 : DEBOUNCE_MS,
        );

        return () => window.clearTimeout(timer);
        // `http` changes on every render; the request only depends on what was typed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, query, result?.query]);

    // Suggestions stay up while the next ones load, so the list doesn't flicker on every keystroke.
    const options = result?.suggestions ?? [];
    const expanded = open && options.length > 0;
    const loading = open && result?.query !== query;

    useEffect(() => {
        if (expanded && active >= 0) {
            list.current
                ?.querySelector(`[data-index="${active}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [expanded, active]);

    const change = (next: string) => {
        setValue(next);
        http.setData('q', next.trim());
        setOpen(true);
        setActive(-1);
    };

    const choose = (option: RepositorySuggestion | undefined) => {
        if (!option || option.watched) {
            return;
        }

        setValue(option.fullName);
        http.setData('q', option.fullName);
        setOpen(false);
        setActive(-1);
    };

    /**
     * The next suggestion that can be picked in the given direction, wrapping around and skipping
     * repositories that are already being watched. From the input, ArrowUp starts at the last one.
     */
    const step = (direction: 1 | -1): number => {
        let index = active;

        for (let tried = 0; tried < options.length; tried++) {
            index =
                index === -1
                    ? direction === 1
                        ? 0
                        : options.length - 1
                    : (index + direction + options.length) % options.length;

            if (!options[index].watched) {
                return index;
            }
        }

        return -1;
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp':
                event.preventDefault();

                if (!open) {
                    setOpen(true);

                    return;
                }

                setActive(step(event.key === 'ArrowDown' ? 1 : -1));

                return;
            case 'Enter':
                // Without a highlighted suggestion, Enter submits what was typed.
                if (expanded && active >= 0) {
                    event.preventDefault();
                    choose(options[active]);
                }

                return;
            case 'Escape':
                // Closes the list first, and the dialog it sits in only on the next press.
                if (expanded) {
                    event.preventDefault();
                    setOpen(false);
                    setActive(-1);
                }

                return;
            case 'Tab':
                setOpen(false);

                return;
        }
    };

    return (
        <div className="relative">
            <input
                {...(initialFocus ? INITIAL_FOCUS : {})}
                ref={inputRef}
                id={id}
                name={name}
                value={value}
                onChange={(event) => change(event.target.value)}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={onKeyDown}
                placeholder={placeholder}
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={expanded}
                aria-controls={listId}
                aria-activedescendant={
                    expanded && active >= 0 ? `${listId}-${active}` : undefined
                }
                autoComplete="off"
                autoCapitalize="off"
                autoCorrect="off"
                spellCheck={false}
                className="h-10 w-full rounded-lg border border-line bg-page px-3 text-sm placeholder:text-ink-muted focus:border-accent focus:ring-2 focus:ring-accent-soft focus:outline-none"
            />

            <div
                hidden={!expanded}
                className={cn(
                    'absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-xl border border-line bg-surface-raised text-sm shadow-lg shadow-black/10 transition-opacity',
                    loading && 'opacity-70',
                )}
            >
                <ul
                    ref={list}
                    id={listId}
                    role="listbox"
                    aria-label="Repositories on GitHub"
                    className="max-h-72 overflow-y-auto p-1"
                >
                    {options.map((option, index) => (
                        <li
                            key={option.fullName}
                            id={`${listId}-${index}`}
                            data-index={index}
                            role="option"
                            aria-selected={index === active}
                            aria-disabled={option.watched || undefined}
                            // Keeps focus in the input, so the list doesn't close before the click lands.
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseMove={() =>
                                !option.watched &&
                                index !== active &&
                                setActive(index)
                            }
                            onClick={() => choose(option)}
                            className={cn(
                                'flex cursor-default items-center gap-2.5 rounded-lg px-2 py-1.5',
                                index === active && 'bg-line/60',
                                option.watched && 'opacity-60',
                            )}
                        >
                            <Avatar
                                src={option.avatarUrl}
                                login={option.owner}
                                size={20}
                            />
                            <div className="min-w-0 flex-1">
                                <div className="truncate">
                                    <span className="text-ink-secondary">
                                        {option.owner}/
                                    </span>
                                    <span className="font-medium text-ink">
                                        {option.name}
                                    </span>
                                </div>
                                {option.description && (
                                    <div className="truncate text-xs text-ink-muted">
                                        {option.description}
                                    </div>
                                )}
                            </div>
                            <div className="flex shrink-0 items-center gap-2 text-[11px] text-ink-muted">
                                {option.watched ? (
                                    <span>Watching</span>
                                ) : (
                                    option.private && (
                                        <span className="inline-flex items-center gap-1">
                                            <LockIcon width={11} height={11} />
                                            Private
                                        </span>
                                    )
                                )}
                                {option.stars !== null && option.stars > 0 && (
                                    <span
                                        className="inline-flex items-center gap-0.5 tabular-nums"
                                        aria-label={`${option.stars} stars`}
                                    >
                                        <StarIcon width={11} height={11} />
                                        {formatNumber(option.stars)}
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
                {result && !result.complete && (
                    <p className="border-t border-line px-3 py-1.5 text-[11px] text-ink-muted">
                        GitHub didn't answer in full, so some repositories may
                        be missing.
                    </p>
                )}
            </div>

            <span role="status" className="sr-only">
                {expanded
                    ? `${options.length} ${options.length === 1 ? 'repository' : 'repositories'} suggested`
                    : ''}
            </span>
        </div>
    );
}
