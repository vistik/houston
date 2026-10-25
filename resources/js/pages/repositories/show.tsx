import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronLeftIcon, ExternalIcon, TrashIcon } from '@/components/icons';
import { HistoryGap } from '@/components/repository/history-gap';
import {
    ReportOptions,
    RepositoryActions,
} from '@/components/repository/toolbar';
import {
    REPOSITORY_VIEW_OPTIONS,
    REPOSITORY_VIEWS,
    switchRepositoryView,
} from '@/components/repository/views';
import type { RepositoryView } from '@/components/repository/views';
import { VisibilityBadge } from '@/components/repository/visibility-badge';
import { Grid } from '@/components/report/grid';
import { SkeletonCell } from '@/components/report/widgets';
import { SyncStatus } from '@/components/sync-status';
import { Avatar } from '@/components/ui/avatar';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import { useLookbackConfirmation } from '@/hooks/use-lookback-confirmation';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useReloadOnChange } from '@/hooks/use-reload-on-change';
import AppLayout from '@/layouts/app-layout';
import { MetricHistoryContext, metricHistory } from '@/lib/metric-history';
import {
    isCustomPeriod,
    periodLabel,
    periodQuery,
    ReportPeriod,
} from '@/lib/periods';
import type { Granularity, PeriodFilters } from '@/lib/periods';
import { TimelineEvents } from '@/lib/timeline-events';
import { destroy, index, show, wall } from '@/routes/repositories';
import type {
    DependencyOverview,
    FocusedTimelineEvent,
    RepositoryInfo,
    TimelineEvent,
    VelocityReport,
} from '@/types';

type Props = {
    repository: RepositoryInfo;
    repositories: {
        id: number;
        owner: string;
        name: string;
        fullName: string;
        avatarUrl: string | null;
    }[];
    filters: PeriodFilters & {
        granularity: Granularity | null;
        complete: boolean;
        bots: boolean;
        /** The timeline event the period runs from, through today. */
        event: number | null;
    };
    events: TimelineEvent[];
    event: FocusedTimelineEvent | null;
    report: VelocityReport;
    dependencies: DependencyOverview | null;
    view: RepositoryView;
};

export default function RepositoryShow({
    repository,
    repositories,
    filters,
    events,
    event,
    report,
    dependencies,
    view,
}: Props) {
    const { demo } = usePage().props;
    // A view this build doesn't know, e.g. one saved by a newer one, falls back to the full report.
    const View = (REPOSITORY_VIEWS[view] ?? REPOSITORY_VIEWS.full).component;

    // Poll only the cheap sync state, and fetch the report again once a sync brings in new data.
    usePollWhile(repository.sync.inProgress, ['repository']);
    useReloadOnChange(
        `${repository.sync.hasData}:${repository.sync.finishedAt}`,
        ['report', 'dependencies'],
    );

    const visit = (
        owner: string,
        name: string,
        changes: Partial<Props['filters']>,
    ) => {
        // Picking another period leaves the event behind.
        const next = {
            ...filters,
            ...('days' in changes ? { event: null } : {}),
            ...changes,
        };

        router.get(
            show.url(
                { owner, name },
                {
                    query: {
                        ...periodQuery(next),
                        granularity: next.granularity ?? undefined,
                        // Always explicit, so unticking it overrides a default that leaves the current bucket out.
                        complete: next.complete ? 1 : 0,
                        bots: next.bots ? 1 : undefined,
                        event: next.event ?? undefined,
                    },
                },
            ),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const { choosePeriod, dialog } = useLookbackConfirmation(
        [repository],
        filters,
    );

    const nav = (
        <>
            <span className="text-line-strong">/</span>
            <Select
                label="Repository"
                value={repository.fullName}
                onChange={(fullName) => {
                    const target = repositories.find(
                        (entry) => entry.fullName === fullName,
                    );

                    if (target) {
                        visit(target.owner, target.name, {});
                    }
                }}
                className="h-auto max-w-56 border-0 bg-transparent py-1 pl-1 text-sm text-ink hover:bg-line/60"
                options={repositories.map((entry) => ({
                    value: entry.fullName,
                    label: entry.fullName,
                    icon: (
                        <Avatar
                            src={entry.avatarUrl}
                            login={entry.fullName}
                            size={16}
                            className="rounded"
                        />
                    ),
                }))}
            />
        </>
    );

    return (
        <AppLayout nav={nav}>
            <Head title={repository.fullName} />

            <Link
                href={index()}
                className="inline-flex items-center gap-1 text-xs text-ink-muted hover:text-ink"
            >
                <ChevronLeftIcon width={13} height={13} /> All repositories
            </Link>

            <div className="mt-3 flex min-w-0 items-center gap-3">
                <Avatar
                    src={repository.avatarUrl}
                    login={repository.owner}
                    size={40}
                    className="rounded-lg"
                />
                <div className="min-w-0">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <span className="truncate">
                            <span className="text-ink-muted">
                                {repository.owner}/
                            </span>
                            {repository.name}
                        </span>
                        <a
                            href={repository.url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-ink-muted hover:text-ink"
                            aria-label="Open on GitHub"
                        >
                            <ExternalIcon width={16} height={16} />
                        </a>
                        <VisibilityBadge
                            repository={repository}
                            className="tracking-normal"
                        />
                    </h1>
                    {repository.description && (
                        <p className="mt-0.5 truncate text-sm text-ink-secondary">
                            {repository.description}
                        </p>
                    )}
                </div>
            </div>

            <StickyToolbar className="mt-3 justify-end">
                <SyncStatus repository={repository} />
                <ReportOptions
                    filters={filters}
                    granularity={report.period.granularity}
                    days={report.period.days}
                    onChange={(changes) =>
                        choosePeriod({ ...filters, ...changes }, () =>
                            visit(repository.owner, repository.name, changes),
                        )
                    }
                />
                <RepositoryActions
                    repository={repository}
                    view={view}
                    views={REPOSITORY_VIEW_OPTIONS}
                    onViewChange={(next) =>
                        switchRepositoryView(repository, next)
                    }
                    wallUrl={wall.url(
                        { owner: repository.owner, name: repository.name },
                        {
                            query:
                                isCustomPeriod(filters) || filters.all
                                    ? {}
                                    : { days: filters.days },
                        },
                    )}
                />
            </StickyToolbar>
            {dialog}

            {repository.sync.status === 'failed' && repository.sync.error && (
                <div className="mt-6 rounded-lg border border-bad/30 bg-bad-soft px-4 py-3 text-sm text-bad">
                    Houston, we have a problem. The last sync failed:{' '}
                    {repository.sync.error}
                </div>
            )}

            {repository.sync.hasData && (
                <HistoryGap repository={repository} period={filters} />
            )}

            {repository.sync.hasData ? (
                <ReportPeriod value={filters}>
                    <MetricHistoryContext value={metricHistory(report)}>
                        <TimelineEvents
                            value={{
                                events,
                                focused: event,
                                clearHref: show.url({
                                    owner: repository.owner,
                                    name: repository.name,
                                }),
                                bucketDays: report.period.bucketDays,
                                href: (timelineEvent) =>
                                    show.url(
                                        {
                                            owner: repository.owner,
                                            name: repository.name,
                                        },
                                        {
                                            query: {
                                                event: timelineEvent.id,
                                                complete: filters.complete
                                                    ? 1
                                                    : 0,
                                                bots: filters.bots
                                                    ? 1
                                                    : undefined,
                                            },
                                        },
                                    ),
                            }}
                        >
                            <View
                                report={report}
                                periodLabel={periodLabel(filters)}
                                defaultBranch={repository.defaultBranch}
                                repository={repository}
                                dependencies={dependencies}
                            />
                        </TimelineEvents>
                    </MetricHistoryContext>
                </ReportPeriod>
            ) : (
                <Loading />
            )}

            {!demo && (
                <div className="mt-12 flex justify-end border-t border-line pt-6">
                    <StopWatching repository={repository} />
                </div>
            )}
        </AppLayout>
    );
}

function StopWatching({ repository }: { repository: RepositoryInfo }) {
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setConfirming(true)}
                className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs text-ink-muted transition hover:bg-bad-soft hover:text-bad"
            >
                <TrashIcon width={13} height={13} /> Stop watching
            </button>
            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={(visit) =>
                    router.delete(destroy.url(repository.id), visit)
                }
                title={`Stop watching ${repository.fullName}?`}
                description="Its cached pull requests, reviews and CI runs will be removed. You can watch it again later."
                confirmLabel="Stop watching"
                processingLabel="Removing…"
                destructive
            />
        </>
    );
}

function Loading() {
    return (
        <div className="mt-8">
            <Grid>
                {Array.from({ length: 6 }).map((_, index) => (
                    <SkeletonCell key={index} span="1/6" />
                ))}
                <SkeletonCell span="full" className="h-48" />
            </Grid>
            <p className="mt-6 text-center text-sm text-ink-muted">
                Pulling pull requests, reviews and CI runs from GitHub. Large
                repositories can take a few minutes the first time.
            </p>
        </div>
    );
}
