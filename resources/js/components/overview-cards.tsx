import { Link, router, usePage } from '@inertiajs/react';
import { Sparkline } from '@/components/charts/sparkline';
import {
    AlertIcon,
    CheckIcon,
    EllipsisIcon,
    PlusIcon,
} from '@/components/icons';
import {
    BenchmarkBadge,
    DeltaPill,
    MetricLabel,
} from '@/components/metric-card';
import { VisibilityBadge } from '@/components/repository/visibility-badge';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Popover } from '@/components/ui/popover';
import { WatchRepositoryButton } from '@/components/watch-repository';
import { formatMetric, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { MetricHistoryContext, metricHistory } from '@/lib/metric-history';
import type { MetricHistory } from '@/lib/metric-history';
import { periodQuery } from '@/lib/periods';
import {
    cardOptions,
    REPOSITORY_CARD_VIEWS,
    repositoryCard,
    TEAM_CARD_VIEWS,
    teamCard,
} from '@/lib/repository-views';
import type { PeriodFilters } from '@/lib/periods';
import {
    cardView as saveRepositoryCard,
    show as showRepository,
} from '@/routes/repositories';
import { index as teamSettings } from '@/routes/settings/teams';
import { cardView as saveTeamCard, show as showTeam } from '@/routes/teams';
import type {
    Metric,
    RepositoryInfo,
    RepositorySummary,
    TeamInfo,
    TeamSummary,
} from '@/types';
import { buttonClass } from '@/components/ui/button';

/**
 * Busiest repositories first, by pull requests merged in the period. Repositories still waiting on their
 * summary keep their alphabetical order at the end.
 */
export function byMergedPullRequests(
    repositories: RepositoryInfo[],
    summaries: Record<number, RepositorySummary> | undefined,
): RepositoryInfo[] {
    const merged = (repository: RepositoryInfo): number =>
        summaries?.[repository.id]?.kpis.throughput.value ?? -1;

    return [...repositories].sort((a, b) => merged(b) - merged(a));
}

export function RepositoryCard({
    repository,
    summary,
    period,
}: {
    repository: RepositoryInfo;
    summary: RepositorySummary | undefined;
    period: PeriodFilters;
}) {
    const { sync } = repository;

    return (
        // The whole card links to the repository through its name, so the metric labels in it can be
        // focused on their own to explain themselves.
        <div className="group relative flex flex-col rounded-xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(0,0,0,0.03)] transition hover:border-line-strong hover:shadow-md has-[a:focus-visible]:ring-2 has-[a:focus-visible]:ring-accent">
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <Avatar
                        src={repository.avatarUrl}
                        login={repository.owner}
                        size={28}
                        className="rounded-md"
                    />
                    <div className="min-w-0">
                        <div className="flex min-w-0 items-center gap-1.5 text-xs text-ink-muted">
                            <span className="truncate">{repository.owner}</span>
                            <VisibilityBadge
                                repository={repository}
                                className="px-1.5 py-0 text-[10px]"
                            />
                        </div>
                        <Link
                            href={showRepository(
                                {
                                    owner: repository.owner,
                                    name: repository.name,
                                },
                                { query: periodQuery(period) },
                            )}
                            prefetch
                            className="block truncate font-semibold tracking-tight group-hover:text-accent after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                        >
                            {repository.name}
                        </Link>
                    </div>
                </div>
                <div className="flex items-center gap-1">
                    {sync.inProgress ? (
                        <Badge tone="accent">Syncing</Badge>
                    ) : sync.status === 'failed' ? (
                        <Badge tone="bad">
                            <AlertIcon width={11} height={11} /> Sync failed
                        </Badge>
                    ) : (
                        <span className="text-[11px] text-ink-muted">
                            {timeAgo(sync.finishedAt)}
                        </span>
                    )}
                    <CardViewMenu
                        label={`What the ${repository.fullName} card shows`}
                        value={repository.cardView}
                        options={cardOptions(REPOSITORY_CARD_VIEWS)}
                        onChange={(cardView) =>
                            saveCard(
                                saveRepositoryCard.url(repository.id),
                                cardView,
                            )
                        }
                    />
                </div>
            </div>

            {!sync.hasData ? (
                <div className="mt-6 flex h-36 items-center justify-center rounded-lg border border-dashed border-line text-sm text-ink-muted">
                    {sync.status === 'failed'
                        ? sync.error
                        : 'Fetching history from GitHub…'}
                </div>
            ) : !summary ? (
                <CardSkeleton />
            ) : (
                <MetricHistoryContext value={summaryHistory(summary)}>
                    <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-4">
                        {repositoryCard(repository.cardView).card.map(
                            ({ metric, label }) => (
                                <CardMetric
                                    key={metric}
                                    label={label}
                                    metric={summary.kpis[metric]}
                                />
                            ),
                        )}
                    </div>
                    <div className="mt-5">
                        <Sparkline
                            values={summary.trend.map((point) => point.merged)}
                        />
                    </div>
                    <div className="mt-3 flex items-center gap-3 text-xs text-ink-secondary">
                        <span>
                            <span className="font-semibold text-ink">
                                {summary.kpis.openPullRequests.value}
                            </span>{' '}
                            open
                        </span>
                        {(summary.kpis.stalePullRequests.value ?? 0) > 0 && (
                            <Badge tone="warn">
                                {summary.kpis.stalePullRequests.value} stale
                            </Badge>
                        )}
                    </div>
                </MetricHistoryContext>
            )}
        </div>
    );
}

export function TeamCard({
    team,
    summary,
    period,
}: {
    team: TeamInfo;
    summary: TeamSummary | undefined;
    period: PeriodFilters;
}) {
    const current = team.memberships.filter((membership) => membership.current);

    return (
        <div className="group relative flex flex-col rounded-xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(0,0,0,0.03)] transition hover:border-line-strong hover:shadow-md has-[a:focus-visible]:ring-2 has-[a:focus-visible]:ring-accent">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={showTeam(team.slug, {
                            query: periodQuery(period),
                        })}
                        prefetch
                        className="block truncate font-semibold tracking-tight group-hover:text-accent after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                    >
                        {team.name}
                    </Link>
                    <div className="text-xs text-ink-muted">
                        {current.length}{' '}
                        {current.length === 1 ? 'member' : 'members'}
                    </div>
                </div>
                <div className="flex items-center gap-1">
                    <div className="flex -space-x-1.5">
                        {current.slice(0, 6).map((membership) => (
                            <Avatar
                                key={membership.id}
                                src={membership.avatarUrl}
                                login={membership.login}
                                size={24}
                                className="ring-2 ring-surface"
                            />
                        ))}
                    </div>
                    <CardViewMenu
                        label={`What the ${team.name} card shows`}
                        value={team.cardView}
                        options={cardOptions(TEAM_CARD_VIEWS)}
                        onChange={(cardView) =>
                            saveCard(saveTeamCard.url(team.slug), cardView)
                        }
                    />
                </div>
            </div>

            {!summary ? (
                <CardSkeleton />
            ) : (
                <MetricHistoryContext value={summaryHistory(summary)}>
                    <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-4">
                        {teamCard(team.cardView).card.map(
                            ({ metric, label }) => (
                                <CardMetric
                                    key={metric}
                                    label={label}
                                    metric={summary.kpis[metric]}
                                />
                            ),
                        )}
                    </div>
                    <div className="mt-5">
                        <Sparkline
                            values={summary.trend.map((point) => point.merged)}
                        />
                    </div>
                    {(summary.kpis.stalePullRequests.value ?? 0) > 0 && (
                        <div className="mt-3">
                            <Badge tone="warn">
                                {summary.kpis.stalePullRequests.value} stale
                            </Badge>
                        </div>
                    )}
                </MetricHistoryContext>
            )}
        </div>
    );
}

/**
 * Save what a card shows. Only the cards' own data reloads; the summaries already carry every metric a
 * card can show.
 */
function saveCard(url: string, cardView: string) {
    router.put(
        url,
        { card_view: cardView },
        {
            only: ['repositories', 'teams'],
            preserveScroll: true,
            preserveState: true,
        },
    );
}

/**
 * A small menu on a card to pick what it shows. It sits above the link that covers the card.
 */
function CardViewMenu<View extends string>({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: View;
    options: { value: View; label: string }[];
    onChange: (view: View) => void;
}) {
    const item =
        'flex w-full items-center gap-2 rounded-lg py-1.5 pr-2.5 pl-2 text-left whitespace-nowrap text-ink-secondary hover:bg-line/60 hover:text-ink';

    return (
        <Popover
            label={label}
            role="menu"
            className="min-w-36 p-1"
            trigger={({ toggle, ref, open, ...aria }) => (
                <button
                    ref={ref}
                    type="button"
                    onClick={toggle}
                    aria-label={label}
                    className={cn(
                        'relative z-10 inline-flex size-6 cursor-pointer items-center justify-center rounded-md text-ink-muted transition hover:bg-line hover:text-ink focus-visible:opacity-100',
                        open
                            ? 'opacity-100'
                            : 'opacity-0 group-hover:opacity-100 pointer-coarse:opacity-100',
                    )}
                    {...aria}
                >
                    <EllipsisIcon width={14} height={14} />
                </button>
            )}
        >
            {(close) => (
                <div role="group" aria-label="Card shows">
                    <div className="px-2 pt-1 pb-0.5 text-[11px] font-medium text-ink-muted uppercase">
                        Card shows
                    </div>
                    {options.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            role="menuitemradio"
                            aria-checked={option.value === value}
                            onClick={() => {
                                close();

                                if (option.value !== value) {
                                    onChange(option.value);
                                }
                            }}
                            className={item}
                        >
                            <CheckIcon
                                width={14}
                                height={14}
                                className={
                                    option.value === value
                                        ? 'text-accent'
                                        : 'invisible'
                                }
                            />
                            {option.label}
                        </button>
                    ))}
                </div>
            )}
        </Popover>
    );
}

/**
 * A summary's metrics over time. Summaries cached before they carried a series have none.
 */
function summaryHistory(
    summary: RepositorySummary | TeamSummary,
): MetricHistory | null {
    if (!summary.series || !summary.period) {
        return null;
    }

    return metricHistory({
        kpis: summary.kpis,
        series: summary.series,
        trend: summary.trend,
        period: summary.period,
    });
}

export function NoTeams() {
    const { demo } = usePage().props;

    return (
        <div className="mt-8 rounded-xl border border-dashed border-line-strong px-6 py-16 text-center">
            <h2 className="font-semibold">No teams yet</h2>
            <p className="mx-auto mt-1 max-w-md text-sm text-ink-secondary">
                Group people by their GitHub username to see how their pull
                requests move across every watched repository.
            </p>
            {!demo && (
                <Link
                    href={teamSettings()}
                    className={buttonClass({
                        variant: 'primary',
                        size: 'sm',
                        className: 'mt-5',
                    })}
                >
                    <PlusIcon width={13} height={13} />
                    Create a team
                </Link>
            )}
        </div>
    );
}

function CardSkeleton() {
    return (
        <div aria-hidden className="animate-pulse">
            <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index}>
                        <div className="h-3 w-16 rounded bg-line" />
                        <div className="mt-1.5 h-6 w-20 rounded bg-line" />
                    </div>
                ))}
            </div>
            <div className="mt-5 h-9 rounded bg-line" />
            <div className="mt-3 h-4 w-14 rounded bg-line" />
        </div>
    );
}

function CardMetric({ label, metric }: { label: string; metric: Metric }) {
    return (
        <div>
            {/* The label and pills sit above the card's link, so they can explain themselves on hover
                and focus; clicking anywhere else opens the report. */}
            <div className="relative z-10 w-fit max-w-full text-[11px] text-ink-muted">
                <MetricLabel metric={metric}>{label}</MetricLabel>
            </div>
            <div className="mt-0.5 flex flex-wrap items-center gap-1.5">
                <span className="text-lg font-semibold tracking-tight">
                    {formatMetric(metric.value, metric.unit)}
                </span>
                <span className="relative z-10 inline-flex items-center gap-1.5 empty:hidden">
                    <DeltaPill metric={metric} />
                    <BenchmarkBadge metric={metric} label={label} />
                </span>
            </div>
        </div>
    );
}

export function NoRepositories() {
    return (
        <div className="mt-8 rounded-xl border border-dashed border-line-strong px-6 py-16 text-center">
            <h2 className="font-semibold">Nothing on the launch pad yet</h2>
            <p className="mx-auto mt-1 max-w-md text-sm text-ink-secondary">
                Watch a repository to begin the countdown. Houston pulls its
                pull requests, reviews and CI runs from GitHub and keeps them as
                a local cache. Only the list of watched repositories is stored
                permanently.
            </p>
            <WatchRepositoryButton className="mt-5" />
        </div>
    );
}
