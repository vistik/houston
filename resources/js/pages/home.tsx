import { Head, Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { NoRepositories, NoTeams } from '@/components/overview-cards';
import {
    LayoutPicker,
    RepositoryOverview,
    TeamOverview,
} from '@/components/overview-layouts';
import type { OverviewLayouts } from '@/components/overview-layouts';
import { ChevronDownIcon } from '@/components/icons';
import { PeriodPicker } from '@/components/period-picker';
import { Avatar } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover } from '@/components/ui/popover';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import { useLookbackConfirmation } from '@/hooks/use-lookback-confirmation';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useReloadOnChange } from '@/hooks/use-reload-on-change';
import AppLayout from '@/layouts/app-layout';
import { isCustomPeriod, periodQuery, ReportPeriod } from '@/lib/periods';
import { cn } from '@/lib/utils';
import type { PeriodFilters } from '@/lib/periods';
import { home, wall } from '@/routes';
import { pins as savePins } from '@/routes/home';
import { index as repositoriesIndex } from '@/routes/repositories';
import { index as teamsIndex } from '@/routes/teams';
import type {
    RepositoryInfo,
    RepositorySummary,
    TeamInfo,
    TeamSummary,
} from '@/types';
import { buttonClass } from '@/components/ui/button';

type Choice = { id: number; name: string; avatarUrl?: string | null };

/** The ids picked to show on the front page: null shows all of a kind, an empty list hides it. */
type Pins = { teams: number[] | null; repositories: number[] | null };

type Props = {
    filters: PeriodFilters;
    sections: { teams: boolean; repositories: boolean };
    repositories: RepositoryInfo[];
    teams: TeamInfo[];
    choices: { teams: Choice[]; repositories: Choice[] };
    pins: Pins;
    layouts: OverviewLayouts;
    summaries?: Record<number, RepositorySummary>;
    teamSummaries?: Record<number, TeamSummary>;
};

export default function Home({
    filters,
    sections,
    repositories,
    teams,
    choices,
    pins,
    layouts,
    summaries,
    teamSummaries,
}: Props) {
    const syncing = repositories.some(
        (repository) => repository.sync.inProgress,
    );

    // Poll only the cheap sync state, and fetch summaries again once a sync brings in new data.
    usePollWhile(syncing, ['repositories']);
    useReloadOnChange(
        repositories
            .map(({ id, sync }) => `${id}:${sync.hasData}:${sync.finishedAt}`)
            .join('|'),
        ['summaries', 'teamSummaries'],
    );

    const visit = (period: PeriodFilters) =>
        router.get(
            home.url({ query: periodQuery(period) }),
            {},
            { preserveScroll: true },
        );

    const { choosePeriod, dialog } = useLookbackConfirmation(
        repositories,
        filters,
    );

    return (
        <AppLayout>
            <Head title="Overview" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Delivery velocity
                </h1>
                <p className="mt-1 text-sm text-ink-secondary">
                    How fast code moves from first commit to merged, for every
                    team and across every repository you watch.
                </p>
            </div>

            <StickyToolbar className="mt-3 justify-end">
                <Chooser choices={choices} pins={pins} />
                <a
                    href={wall.url({
                        query: isCustomPeriod(filters)
                            ? {}
                            : { days: filters.days },
                    })}
                    className={buttonClass({
                        variant: 'secondary',
                        size: 'sm',
                    })}
                >
                    Wall display
                </a>
                <PeriodPicker
                    value={filters}
                    onChange={(period) =>
                        choosePeriod(period, () => visit(period))
                    }
                />
            </StickyToolbar>
            {dialog}

            <ReportPeriod value={filters}>
                {!sections.teams && !sections.repositories && (
                    <div className="mt-8 rounded-xl border border-dashed border-line-strong px-6 py-16 text-center">
                        <h2 className="font-semibold">Nothing picked</h2>
                        <p className="mx-auto mt-1 max-w-md text-sm text-ink-secondary">
                            Use Choose to pick the teams and repositories this
                            page shows.
                        </p>
                    </div>
                )}

                {sections.teams && (
                    <Group
                        title="Teams"
                        href={teamsIndex.url({ query: periodQuery(filters) })}
                        linkLabel="All teams"
                        actions={
                            teams.length > 0 && (
                                <LayoutPicker kind="teams" layouts={layouts} />
                            )
                        }
                    >
                        {teams.length === 0 ? (
                            <NoTeams />
                        ) : (
                            <TeamOverview
                                className="mt-3"
                                layout={layouts.teams}
                                teams={teams}
                                summaries={teamSummaries}
                                period={filters}
                            />
                        )}
                    </Group>
                )}

                {sections.repositories && (
                    <Group
                        title="Repositories"
                        href={repositoriesIndex.url({
                            query: periodQuery(filters),
                        })}
                        linkLabel="All repositories"
                        actions={
                            repositories.length > 0 && (
                                <LayoutPicker
                                    kind="repositories"
                                    layouts={layouts}
                                />
                            )
                        }
                    >
                        {repositories.length === 0 ? (
                            <NoRepositories />
                        ) : (
                            <RepositoryOverview
                                className="mt-3"
                                layout={layouts.repositories}
                                repositories={repositories}
                                summaries={summaries}
                                period={filters}
                            />
                        )}
                    </Group>
                )}
            </ReportPeriod>
        </AppLayout>
    );
}

function Group({
    title,
    href,
    linkLabel,
    actions,
    children,
}: {
    title: string;
    href: string;
    linkLabel: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="mt-8">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-sm font-semibold text-ink">{title}</h2>
                <div className="flex items-center gap-4">
                    {actions}
                    <Link
                        href={href}
                        className="text-xs text-ink-muted hover:text-ink"
                    >
                        {linkLabel} →
                    </Link>
                </div>
            </div>
            {children}
        </section>
    );
}

/**
 * Pick the teams and repositories the front page shows. Picking every one of a kind shows all of it,
 * including ones added later, and picking none hides its section.
 */
function Chooser({ choices, pins }: { choices: Props['choices']; pins: Pins }) {
    const picked = (kind: keyof Pins): number[] =>
        pins[kind] ?? choices[kind].map((choice) => choice.id);

    const save = (next: Pins) =>
        router.put(savePins.url(), next, {
            preserveScroll: true,
            preserveState: true,
        });

    const pick = (kind: keyof Pins, ids: number[] | null) =>
        save({
            ...pins,
            [kind]:
                ids === null || ids.length === choices[kind].length
                    ? null
                    : [...ids].sort((a, b) => a - b),
        });

    const toggle = (kind: keyof Pins, id: number, checked: boolean) =>
        pick(
            kind,
            checked
                ? [...picked(kind), id]
                : picked(kind).filter((pickedId) => pickedId !== id),
        );

    const isCustomised = pins.teams !== null || pins.repositories !== null;
    const pickedCount = picked('teams').length + picked('repositories').length;

    return (
        <Popover
            label="Choose what the front page shows"
            className="flex max-h-[70vh] w-80 flex-col gap-4 overflow-y-auto p-4"
            trigger={({ open, toggle: toggleOpen, ref, ...aria }) => (
                <button
                    ref={ref}
                    type="button"
                    onClick={toggleOpen}
                    className={buttonClass({
                        variant: 'secondary',
                        className: 'pr-2 aria-expanded:border-ink-muted',
                    })}
                    {...aria}
                >
                    {isCustomised ? 'Picked' : 'Choose'}
                    {isCustomised && (
                        <span className="text-ink-muted">{pickedCount}</span>
                    )}
                    <ChevronDownIcon
                        width={13}
                        height={13}
                        className={cn(
                            'text-ink-muted transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            )}
        >
            {() => (
                <>
                    {(['teams', 'repositories'] as const).map((kind) => {
                        if (choices[kind].length === 0) {
                            return null;
                        }

                        const title =
                            kind === 'teams' ? 'Teams' : 'Repositories';
                        const pickedIds = picked(kind);

                        return (
                            <fieldset
                                key={kind}
                                className="flex flex-col gap-2.5"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <legend className="text-[11px] font-medium text-ink-muted uppercase">
                                        {title}
                                    </legend>
                                    <div className="flex items-baseline gap-2 text-[11px]">
                                        <GroupAction
                                            label={`Show all ${title.toLowerCase()}`}
                                            disabled={pins[kind] === null}
                                            onClick={() => pick(kind, null)}
                                        >
                                            All
                                        </GroupAction>
                                        <GroupAction
                                            label={`Hide all ${title.toLowerCase()}`}
                                            disabled={pickedIds.length === 0}
                                            onClick={() => pick(kind, [])}
                                        >
                                            None
                                        </GroupAction>
                                    </div>
                                </div>
                                {choices[kind].map((choice) => (
                                    <Checkbox
                                        key={choice.id}
                                        checked={pickedIds.includes(choice.id)}
                                        onChange={(next) =>
                                            toggle(kind, choice.id, next)
                                        }
                                        label={
                                            <span className="inline-flex items-center gap-1.5">
                                                {kind === 'repositories' && (
                                                    <Avatar
                                                        src={choice.avatarUrl}
                                                        login={choice.name}
                                                        size={14}
                                                        className="rounded"
                                                    />
                                                )}
                                                {choice.name}
                                            </span>
                                        }
                                    />
                                ))}
                            </fieldset>
                        );
                    })}
                    {choices.teams.length === 0 &&
                        choices.repositories.length === 0 && (
                            <p className="text-xs text-ink-muted">
                                Nothing to choose from yet.
                            </p>
                        )}
                </>
            )}
        </Popover>
    );
}

/**
 * Pick or clear every choice in a group at once.
 */
function GroupAction({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onClick={onClick}
            className="text-accent hover:underline disabled:cursor-default disabled:text-ink-muted disabled:no-underline"
        >
            {children}
        </button>
    );
}
