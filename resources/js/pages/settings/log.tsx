import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import type { BadgeTone } from '@/components/ui/badge';
import { Panel } from '@/components/ui/panel';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Select } from '@/components/ui/select';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useTimeZone } from '@/hooks/use-time-zone';
import SettingsLayout from '@/layouts/settings-layout';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as logs } from '@/routes/settings/log';
import { buttonClass } from '@/components/ui/button';

type Level = 'info' | 'warning' | 'error';

type SystemEvent = {
    id: number;
    level: Level;
    type: string;
    message: string;
    context: Record<string, unknown> | null;
    createdAt: string;
};

type Filters = {
    level: Level | null;
    category: string | null;
    repository: number | null;
};

type Props = {
    filters: Filters;
    categories: string[];
    repositories: { id: number; fullName: string }[];
    events: {
        data: SystemEvent[];
        currentPage: number;
        lastPage: number;
        total: number;
        previousPageUrl: string | null;
        nextPageUrl: string | null;
    };
    keepDays: number;
};

const LEVEL_TONES: Record<Level, BadgeTone> = {
    info: 'neutral',
    warning: 'warn',
    error: 'bad',
};

export default function LogsIndex({
    filters,
    categories,
    repositories,
    events,
    keepDays,
}: Props) {
    // Follow new events as they happen while looking at the latest ones.
    usePollWhile(events.currentPage === 1, ['events', 'categories'], 5000);

    const filter = (changes: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(logs({ query }).url, {}, { preserveScroll: true });
    };

    return (
        <SettingsLayout>
            <Head title="Log" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-ink-secondary">
                        The flight log. What Houston does behind the scenes:
                        syncs, rate limits, cache and settings changes. Kept for{' '}
                        {keepDays} days.
                    </p>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-2">
                <SegmentedControl<Level | 'all'>
                    label="Level"
                    value={filters.level ?? 'all'}
                    onChange={(level) =>
                        filter({ level: level === 'all' ? null : level })
                    }
                    options={[
                        { value: 'all', label: 'All' },
                        { value: 'info', label: 'Info' },
                        { value: 'warning', label: 'Warnings' },
                        { value: 'error', label: 'Errors' },
                    ]}
                />
                <FilterSelect
                    label="Category"
                    value={filters.category ?? ''}
                    onChange={(category) => filter({ category })}
                    options={categories.map((category) => ({
                        value: category,
                        label: category,
                    }))}
                    allLabel="All categories"
                />
                <FilterSelect
                    label="Repository"
                    value={filters.repository?.toString() ?? ''}
                    onChange={(repository) =>
                        filter({
                            repository:
                                repository === '' ? null : Number(repository),
                        })
                    }
                    options={repositories.map((repository) => ({
                        value: repository.id.toString(),
                        label: repository.fullName,
                    }))}
                    allLabel="All repositories"
                />
            </div>

            <Panel className="mt-3" bodyClassName="p-0">
                {events.data.length === 0 ? (
                    <p className="p-8 text-center text-sm text-ink-muted">
                        Nothing in the flight log yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-line">
                        {events.data.map((event) => (
                            <EventRow key={event.id} event={event} />
                        ))}
                    </ul>
                )}
            </Panel>

            {events.lastPage > 1 && (
                <nav className="mt-4 flex items-center justify-between text-xs text-ink-muted">
                    <span>
                        Page {events.currentPage} of {events.lastPage} ·{' '}
                        {events.total} events
                    </span>
                    <div className="flex gap-2">
                        <PageLink href={events.previousPageUrl}>Newer</PageLink>
                        <PageLink href={events.nextPageUrl}>Older</PageLink>
                    </div>
                </nav>
            )}
        </SettingsLayout>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
    allLabel,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    allLabel: string;
}) {
    return (
        <Select
            label={label}
            value={value}
            onChange={onChange}
            options={[{ value: '', label: allLabel }, ...options]}
        />
    );
}

function PageLink({
    href,
    children,
}: {
    href: string | null;
    children: string;
}) {
    const className = buttonClass({ variant: 'secondary', size: 'sm' });

    return href ? (
        <Link href={href} preserveScroll className={className}>
            {children}
        </Link>
    ) : (
        <span className={cn(className, 'opacity-50')}>{children}</span>
    );
}

function EventRow({ event }: { event: SystemEvent }) {
    const [open, setOpen] = useState(false);
    const timeZone = useTimeZone();
    const createdAt = new Date(event.createdAt);

    return (
        <li className="px-5 py-3.5">
            <div className="flex flex-col gap-1.5 sm:flex-row sm:items-baseline sm:gap-4">
                <time
                    dateTime={event.createdAt}
                    title={`${createdAt.toLocaleString('en-GB', { timeZone })} · ${timeAgo(event.createdAt)}`}
                    className="w-32 shrink-0 text-xs text-ink-muted tabular-nums"
                >
                    {createdAt.toLocaleDateString('en', {
                        month: 'short',
                        day: 'numeric',
                        timeZone,
                    })}{' '}
                    {createdAt.toLocaleTimeString('en-GB', { timeZone })}
                </time>
                <div className="flex shrink-0 items-center gap-2 sm:w-44">
                    <Badge tone={LEVEL_TONES[event.level]}>{event.level}</Badge>
                    <span className="truncate text-xs text-ink-muted">
                        {event.type}
                    </span>
                </div>
                <div className="min-w-0 flex-1 text-sm">
                    <span
                        className={cn(
                            'break-words',
                            event.level === 'error' && 'text-bad',
                        )}
                    >
                        {event.message}
                    </span>
                </div>
                {event.context && (
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        aria-expanded={open}
                        className="shrink-0 self-start text-xs text-ink-muted transition hover:text-ink sm:self-auto"
                    >
                        {open ? 'Hide details' : 'Details'}
                    </button>
                )}
            </div>
            {open && event.context && (
                <pre className="mt-2 overflow-x-auto rounded-lg bg-page p-3 font-mono text-[11px] text-ink-secondary sm:ml-36">
                    {JSON.stringify(event.context, null, 2)}
                </pre>
            )}
        </li>
    );
}
