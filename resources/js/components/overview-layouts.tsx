import { Link, router } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import { Sparkline } from '@/components/charts/sparkline';
import { DeltaPill } from '@/components/metric-card';
import {
    byMergedPullRequests,
    RepositoryCard,
    TeamCard,
} from '@/components/overview-cards';
import { AlertIcon } from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { formatMetric, timeAgo } from '@/lib/format';
import { periodQuery } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { cn } from '@/lib/utils';
import { update as saveLayouts } from '@/routes/overview-layouts';
import { show as showRepository } from '@/routes/repositories';
import { show as showTeam } from '@/routes/teams';
import type {
    Metric,
    RepositoryInfo,
    RepositorySummary,
    TeamInfo,
    TeamSummary,
} from '@/types';

/** The ways an overview can lay out its teams or repositories, matching App\Enums\OverviewLayout. */
export type OverviewLayout = 'cards' | 'table';

/** The saved layout for each kind, shared by the front page and the teams and repositories pages. */
export type OverviewLayouts = {
    teams: OverviewLayout;
    repositories: OverviewLayout;
};

type RepositoryTemplateProps = {
    repositories: RepositoryInfo[];
    summaries: Record<number, RepositorySummary> | undefined;
    period: PeriodFilters;
    className?: string;
};

type TeamTemplateProps = {
    teams: TeamInfo[];
    summaries: Record<number, TeamSummary> | undefined;
    period: PeriodFilters;
    className?: string;
};

type Template<Props> = { label: string; component: ComponentType<Props> };

/**
 * Every way to lay out repositories, keyed by layout. Adding a case to OverviewLayout means adding a
 * template here, and the picker offers it straight away.
 */
const REPOSITORY_TEMPLATES = {
    cards: { label: 'Cards', component: RepositoryCards },
    table: { label: 'Table', component: RepositoryTable },
} satisfies Record<OverviewLayout, Template<RepositoryTemplateProps>>;

/** Every way to lay out teams, keyed by layout. */
const TEAM_TEMPLATES = {
    cards: { label: 'Cards', component: TeamCards },
    table: { label: 'Table', component: TeamTable },
} satisfies Record<OverviewLayout, Template<TeamTemplateProps>>;

const TEMPLATES = {
    repositories: REPOSITORY_TEMPLATES,
    teams: TEAM_TEMPLATES,
};

/** Repositories in the saved layout, busiest first. */
export function RepositoryOverview({
    layout,
    repositories,
    ...props
}: RepositoryTemplateProps & { layout: OverviewLayout }) {
    const { component: Template } =
        REPOSITORY_TEMPLATES[layout] ?? REPOSITORY_TEMPLATES.cards;

    return (
        <Template
            repositories={byMergedPullRequests(repositories, props.summaries)}
            {...props}
        />
    );
}

/** Teams in the saved layout. */
export function TeamOverview({
    layout,
    ...props
}: TeamTemplateProps & { layout: OverviewLayout }) {
    const { component: Template } =
        TEAM_TEMPLATES[layout] ?? TEAM_TEMPLATES.cards;

    return <Template {...props} />;
}

/**
 * Switch the layout of teams or repositories. The page switches right away and the choice is saved
 * for every overview of that kind.
 */
export function LayoutPicker({
    kind,
    layouts,
}: {
    kind: keyof OverviewLayouts;
    layouts: OverviewLayouts;
}) {
    const options = (
        Object.entries(TEMPLATES[kind]) as [OverviewLayout, { label: string }][]
    ).map(([value, { label }]) => ({ value, label }));

    return (
        <SegmentedControl
            label={kind === 'teams' ? 'Team layout' : 'Repository layout'}
            options={options}
            value={layouts[kind]}
            onChange={(layout) =>
                router
                    .optimistic<{ layouts: OverviewLayouts }>((props) => ({
                        layouts: { ...props.layouts, [kind]: layout },
                    }))
                    .put(
                        saveLayouts.url(),
                        { [kind]: layout },
                        {
                            only: ['layouts'],
                            preserveScroll: true,
                            preserveState: true,
                        },
                    )
            }
        />
    );
}

function CardGrid({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid gap-4 md:grid-cols-2 xl:grid-cols-3',
                className,
            )}
        >
            {children}
        </div>
    );
}

function RepositoryCards({
    repositories,
    summaries,
    period,
    className,
}: RepositoryTemplateProps) {
    return (
        <CardGrid className={className}>
            {repositories.map((repository) => (
                <RepositoryCard
                    key={repository.id}
                    repository={repository}
                    summary={summaries?.[repository.id]}
                    period={period}
                />
            ))}
        </CardGrid>
    );
}

function TeamCards({ teams, summaries, period, className }: TeamTemplateProps) {
    return (
        <CardGrid className={className}>
            {teams.map((team) => (
                <TeamCard
                    key={team.id}
                    team={team}
                    summary={summaries?.[team.id]}
                    period={period}
                />
            ))}
        </CardGrid>
    );
}

type TableRow = {
    key: number;
    href: string;
    /** The name column, which links the whole row. */
    lead: ReactNode;
    /** One cell per column, still loading, or a message spanning every column. */
    cells: ReactNode[] | 'loading' | { message: ReactNode };
};

function RepositoryTable({
    repositories,
    summaries,
    period,
    className,
}: RepositoryTemplateProps) {
    return (
        <OverviewTable
            className={className}
            label="Repositories"
            leadHeader="Repository"
            columns={[
                'Merged PRs',
                'Time to merge',
                'Pickup time',
                'CI failure rate',
                'Change failure rate',
                'Open',
                'Trend',
            ]}
            rows={repositories.map((repository) => {
                const { sync } = repository;
                const summary = summaries?.[repository.id];

                return {
                    key: repository.id,
                    href: showRepository.url(
                        { owner: repository.owner, name: repository.name },
                        { query: periodQuery(period) },
                    ),
                    lead: (
                        <span className="flex min-w-0 items-center gap-2">
                            <Avatar
                                src={repository.avatarUrl}
                                login={repository.owner}
                                size={18}
                                className="rounded"
                            />
                            <span className="truncate">
                                <span className="text-ink-muted">
                                    {repository.owner}/
                                </span>
                                <span className="font-medium group-hover:text-accent">
                                    {repository.name}
                                </span>
                            </span>
                            {sync.inProgress ? (
                                <Badge tone="accent">Syncing</Badge>
                            ) : sync.status === 'failed' ? (
                                <Badge tone="bad">
                                    <AlertIcon width={11} height={11} /> Sync
                                    failed
                                </Badge>
                            ) : (
                                <span className="text-[11px] whitespace-nowrap text-ink-muted">
                                    {timeAgo(sync.finishedAt)}
                                </span>
                            )}
                        </span>
                    ),
                    cells: !sync.hasData
                        ? {
                              message:
                                  sync.status === 'failed'
                                      ? sync.error
                                      : 'Fetching history from GitHub…',
                          }
                        : !summary
                          ? 'loading'
                          : [
                                <MetricCell
                                    key="throughput"
                                    metric={summary.kpis.throughput}
                                />,
                                <MetricCell
                                    key="cycleTime"
                                    metric={summary.kpis.cycleTime}
                                />,
                                <MetricCell
                                    key="pickupTime"
                                    metric={summary.kpis.pickupTime}
                                />,
                                <MetricCell
                                    key="ciFailureRate"
                                    metric={summary.kpis.ciFailureRate}
                                />,
                                <MetricCell
                                    key="changeFailureRate"
                                    metric={summary.kpis.changeFailureRate}
                                />,
                                <OpenCell
                                    key="open"
                                    open={summary.kpis.openPullRequests}
                                    stale={summary.kpis.stalePullRequests}
                                />,
                                <TrendCell key="trend" trend={summary.trend} />,
                            ],
                };
            })}
        />
    );
}

function TeamTable({ teams, summaries, period, className }: TeamTemplateProps) {
    return (
        <OverviewTable
            className={className}
            label="Teams"
            leadHeader="Team"
            columns={[
                'Merged PRs',
                'Time to merge',
                'Pickup time',
                'Open',
                'Trend',
            ]}
            rows={teams.map((team) => {
                const current = team.memberships.filter(
                    (membership) => membership.current,
                );
                const summary = summaries?.[team.id];

                return {
                    key: team.id,
                    href: showTeam.url(team.slug, {
                        query: periodQuery(period),
                    }),
                    lead: (
                        <span className="flex min-w-0 items-center gap-2.5">
                            <span className="truncate font-medium group-hover:text-accent">
                                {team.name}
                            </span>
                            <span className="flex shrink-0 -space-x-1">
                                {current.slice(0, 4).map((membership) => (
                                    <Avatar
                                        key={membership.id}
                                        src={membership.avatarUrl}
                                        login={membership.login}
                                        size={18}
                                        className="ring-2 ring-surface"
                                    />
                                ))}
                            </span>
                            <span className="text-[11px] whitespace-nowrap text-ink-muted">
                                {current.length}{' '}
                                {current.length === 1 ? 'member' : 'members'}
                            </span>
                        </span>
                    ),
                    cells: !summary
                        ? 'loading'
                        : [
                              <MetricCell
                                  key="throughput"
                                  metric={summary.kpis.throughput}
                              />,
                              <MetricCell
                                  key="cycleTime"
                                  metric={summary.kpis.cycleTime}
                              />,
                              <MetricCell
                                  key="pickupTime"
                                  metric={summary.kpis.pickupTime}
                              />,
                              <OpenCell
                                  key="open"
                                  open={summary.kpis.openPullRequests}
                                  stale={summary.kpis.stalePullRequests}
                              />,
                              <TrendCell key="trend" trend={summary.trend} />,
                          ],
                };
            })}
        />
    );
}

/**
 * One row per team or repository with the headline metrics side by side, so many can be compared at
 * a glance. The name links the whole row.
 */
function OverviewTable({
    label,
    leadHeader,
    columns,
    rows,
    className,
}: {
    label: string;
    leadHeader: string;
    columns: string[];
    rows: TableRow[];
    className?: string;
}) {
    return (
        <div
            className={cn(
                'overflow-x-auto rounded-xl border border-line bg-surface shadow-[0_1px_2px_rgba(0,0,0,0.03)]',
                className,
            )}
        >
            <table className="w-full min-w-[56rem] text-sm" aria-label={label}>
                <thead>
                    <tr className="border-b border-line text-left text-[11px] font-medium text-ink-muted">
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            {leadHeader}
                        </th>
                        {columns.map((column) => (
                            <th
                                key={column}
                                scope="col"
                                className="px-3 py-2.5 font-medium whitespace-nowrap"
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {rows.map((row) => (
                        <tr
                            key={row.key}
                            className="group relative transition-colors hover:bg-page"
                        >
                            <th
                                scope="row"
                                className="max-w-80 px-4 py-2.5 text-left font-normal"
                            >
                                <Link
                                    href={row.href}
                                    prefetch
                                    className="after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:ring-2 after:focus-visible:ring-accent after:focus-visible:ring-inset"
                                >
                                    {row.lead}
                                </Link>
                            </th>
                            {row.cells === 'loading' ? (
                                columns.map((column) => (
                                    <td key={column} className="px-3 py-2.5">
                                        <div
                                            aria-hidden
                                            className="h-4 w-14 animate-pulse rounded bg-line"
                                        />
                                    </td>
                                ))
                            ) : Array.isArray(row.cells) ? (
                                row.cells.map((cell, index) => (
                                    <td
                                        key={columns[index]}
                                        className="px-3 py-2.5 whitespace-nowrap"
                                    >
                                        {cell}
                                    </td>
                                ))
                            ) : (
                                <td
                                    colSpan={columns.length}
                                    className="truncate px-3 py-2.5 text-xs text-ink-muted"
                                >
                                    {row.cells.message}
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MetricCell({ metric }: { metric: Metric }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="tabular font-semibold tracking-tight">
                {formatMetric(metric.value, metric.unit, true)}
            </span>
            {/* Above the row link, so the comparison can be read on hover. */}
            <span className="relative">
                <DeltaPill metric={metric} />
            </span>
        </span>
    );
}

function OpenCell({ open, stale }: { open: Metric; stale: Metric }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className="tabular font-semibold">
                {formatMetric(open.value, open.unit)}
            </span>
            {(stale.value ?? 0) > 0 && (
                <Badge tone="warn">{stale.value} stale</Badge>
            )}
        </span>
    );
}

function TrendCell({ trend }: { trend: { merged: number }[] }) {
    return (
        <Sparkline
            values={trend.map((point) => point.merged)}
            height={22}
            className="w-24"
        />
    );
}
