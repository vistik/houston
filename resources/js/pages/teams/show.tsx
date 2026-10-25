import { Head, Link, router, usePage } from '@inertiajs/react';
import { StageBar } from '@/components/charts/stage-bar';
import type { TimeSeriesChartProps } from '@/components/charts/time-series-chart';
import { Headline } from '@/components/event-comparison';
import { ChevronLeftIcon } from '@/components/icons';
import { BenchmarkBadge } from '@/components/metric-card';
import { Remediations } from '@/components/repository/attention';
import { FlowAndReviewQuality } from '@/components/repository/flow-and-review-quality';
import { ActiveDevelopers } from '@/components/repository/people';
import { SlowestMerged } from '@/components/repository/slowest-merged';
import { ReportOptions } from '@/components/repository/toolbar';
import { WorkInProgress } from '@/components/repository/work-in-progress';
import { Cell, Grid } from '@/components/report/grid';
import {
    ChartPanel,
    MetricTile,
    PanelCell,
    Section,
} from '@/components/report/widgets';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Select } from '@/components/ui/select';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import AppLayout from '@/layouts/app-layout';
import { MetricHistoryContext, metricHistory } from '@/lib/metric-history';
import { bucketThresholds } from '@/lib/benchmarks';
import { formatDate, formatDuration, formatNumber } from '@/lib/format';
import {
    bucketLabel,
    periodLabel,
    periodQuery,
    ReportPeriod,
} from '@/lib/periods';
import type { Granularity, PeriodFilters } from '@/lib/periods';
import { TimelineEvents, useComparedWith } from '@/lib/timeline-events';
import { index as teamSettings } from '@/routes/settings/teams';
import { index, show } from '@/routes/teams';
import type {
    FocusedTimelineEvent,
    TeamInfo,
    TeamReport,
    TimelineEvent,
} from '@/types';
import { buttonClass } from '@/components/ui/button';

type Props = {
    team: TeamInfo;
    teams: { id: number; name: string; slug: string }[];
    /** The repositories the team opened or merged pull requests in during the period, with how many. */
    repositories: {
        fullName: string;
        avatarUrl: string | null;
        pullRequests: number;
    }[];
    filters: PeriodFilters & {
        granularity: Granularity | null;
        complete: boolean;
        /** The owner/name of the one repository to report on, or null for all of them. */
        repository: string | null;
        /** The timeline event the period runs from, through today. */
        event: number | null;
    };
    events: TimelineEvent[];
    event: FocusedTimelineEvent | null;
    report: TeamReport;
};

const ALL_REPOSITORIES = 'all';

export default function TeamShow({
    team,
    teams,
    repositories,
    filters,
    events,
    event,
    report,
}: Props) {
    const { demo } = usePage().props;
    const visit = (slug: string, changes: Partial<Props['filters']>) => {
        // Picking another period leaves the event behind.
        const next = {
            ...filters,
            ...('days' in changes ? { event: null } : {}),
            ...changes,
        };

        router.get(
            show.url(slug, {
                query: {
                    ...periodQuery(next),
                    granularity: next.granularity ?? undefined,
                    // Always explicit, so unticking it overrides a default that leaves the current bucket out.
                    complete: next.complete ? 1 : 0,
                    repository: next.repository ?? undefined,
                    event: next.event ?? undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const current = team.memberships.filter((membership) => membership.current);
    const formerCount = new Set(
        team.memberships
            .filter((membership) => !membership.current)
            .map((membership) => membership.login.toLowerCase()),
    ).size;

    const nav = (
        <>
            <span className="text-line-strong">/</span>
            <Select
                label="Team"
                value={team.slug}
                onChange={(slug) => visit(slug, {})}
                className="h-auto max-w-56 border-0 bg-transparent py-1 pl-1 text-sm text-ink hover:bg-line/60"
                options={teams.map((entry) => ({
                    value: entry.slug,
                    label: entry.name,
                }))}
            />
        </>
    );

    return (
        <AppLayout nav={nav}>
            <Head title={team.name} />

            <Link
                href={index()}
                className="inline-flex items-center gap-1 text-xs text-ink-muted hover:text-ink"
            >
                <ChevronLeftIcon width={13} height={13} /> All teams
            </Link>

            <div className="mt-3 min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {team.name}
                </h1>
                <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-secondary">
                    {current.length === 0 ? (
                        <span>Nobody is on this team right now.</span>
                    ) : (
                        <ul className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            {current.map((membership) => (
                                <li
                                    key={membership.id}
                                    className="flex items-center gap-1.5"
                                >
                                    <Avatar
                                        src={membership.avatarUrl}
                                        login={membership.login}
                                    />
                                    {membership.login}
                                </li>
                            ))}
                        </ul>
                    )}
                    {formerCount > 0 && (
                        <span className="text-xs text-ink-muted">
                            +{formerCount} former member
                            {formerCount === 1 ? '' : 's'}
                        </span>
                    )}
                </div>
            </div>

            <StickyToolbar className="mt-3 justify-end">
                {!demo && (
                    <Link
                        href={teamSettings()}
                        className={buttonClass({
                            variant: 'secondary',
                            size: 'sm',
                        })}
                    >
                        Manage teams
                    </Link>
                )}
                {repositories.length > 1 && (
                    <Select
                        label="Repository"
                        value={filters.repository ?? ALL_REPOSITORIES}
                        onChange={(repository) =>
                            visit(team.slug, {
                                repository:
                                    repository === ALL_REPOSITORIES
                                        ? null
                                        : repository,
                            })
                        }
                        className="h-8 max-w-64 text-xs"
                        options={[
                            {
                                value: ALL_REPOSITORIES,
                                label: `All repositories (${formatNumber(
                                    repositories.reduce(
                                        (total, repository) =>
                                            total + repository.pullRequests,
                                        0,
                                    ),
                                )})`,
                            },
                            ...repositories.map((repository) => ({
                                value: repository.fullName,
                                label: `${repository.fullName} (${formatNumber(repository.pullRequests)})`,
                                icon: (
                                    <Avatar
                                        src={repository.avatarUrl}
                                        login={repository.fullName}
                                        size={16}
                                        className="rounded"
                                    />
                                ),
                            })),
                        ]}
                    />
                )}
                <ReportOptions
                    filters={filters}
                    granularity={report.period.granularity}
                    days={report.period.days}
                    onChange={(changes) => visit(team.slug, changes)}
                />
            </StickyToolbar>

            {team.memberships.length === 0 ? (
                <div className="mt-8 rounded-xl border border-dashed border-line-strong px-6 py-16 text-center">
                    <h2 className="font-semibold">No members yet</h2>
                    <p className="mx-auto mt-1 max-w-md text-sm text-ink-secondary">
                        Add people by their GitHub username in{' '}
                        {demo ? (
                            'the team settings'
                        ) : (
                            <Link
                                href={teamSettings()}
                                className="text-accent hover:underline"
                            >
                                the team settings
                            </Link>
                        )}
                        , and their pull requests across every watched
                        repository show up here.
                    </p>
                </div>
            ) : (
                <ReportPeriod value={filters}>
                    <MetricHistoryContext value={metricHistory(report)}>
                        <TimelineEvents
                            value={{
                                events,
                                focused: event,
                                clearHref: show.url(team.slug),
                                bucketDays: report.period.bucketDays,
                                href: (timelineEvent) =>
                                    show.url(team.slug, {
                                        query: {
                                            event: timelineEvent.id,
                                            complete: filters.complete ? 1 : 0,
                                            repository:
                                                filters.repository ?? undefined,
                                        },
                                    }),
                            }}
                        >
                            <Report
                                report={report}
                                periodLabel={periodLabel(filters)}
                                repository={filters.repository}
                            />
                        </TimelineEvents>
                    </MetricHistoryContext>
                </ReportPeriod>
            )}
        </AppLayout>
    );
}

function Report({
    report,
    periodLabel,
    repository,
}: {
    report: TeamReport;
    periodLabel: string;
    repository: string | null;
}) {
    const { kpis, trend, trends, period } = report;
    const comparedWith = useComparedWith();
    const labels = trend.map((point) => formatDate(point.date));
    const tooltipLabels = trend.map((point) => bucketLabel(point.date, period));
    const dates = trend.map((point) => point.date);

    const charts = {
        throughput: {
            kind: 'bar',
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.throughput, period.bucketDays),
            series: [
                {
                    key: 'merged',
                    label: 'Merged',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.merged),
                },
                {
                    key: 'opened',
                    label: 'Opened',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.opened),
                },
            ],
            format: (value) => formatNumber(value),
        },
        openPullRequests: {
            kind: 'line',
            labels,
            tooltipLabels,
            dates,
            series: [
                {
                    key: 'open',
                    label: 'Ready for review',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.open),
                },
                {
                    key: 'openDrafts',
                    label: 'Drafts',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.openDrafts),
                },
            ],
            format: (value) => formatNumber(value),
        },
        timeToMerge: {
            kind: 'line',
            durationAxis: true,
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.cycleTime, period.bucketDays),
            series: [
                {
                    key: 'cycle',
                    label: 'Time to merge',
                    color: 'var(--series-1)',
                    values: trend.map((point) => point.cycleTime),
                },
            ],
            format: formatDuration,
        },
        pickupTime: {
            kind: 'line',
            durationAxis: true,
            labels,
            tooltipLabels,
            dates,
            benchmark: bucketThresholds(kpis.pickupTime, period.bucketDays),
            series: [
                {
                    key: 'pickup',
                    label: 'Pickup time',
                    color: 'var(--series-2)',
                    values: trend.map((point) => point.pickupTime),
                },
            ],
            format: formatDuration,
        },
    } satisfies Record<string, TimeSeriesChartProps>;

    return (
        <>
            <Grid className="mt-8">
                <Headline>
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Time to merge"
                        hint="TTL · opened → merged, median"
                        icon="cycleTime"
                        metric={kpis.cycleTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Pickup time"
                        hint="Ready → first response"
                        icon="pickupTime"
                        metric={kpis.pickupTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Review time"
                        hint="First response → merged"
                        icon="reviewTime"
                        metric={kpis.reviewTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Merged PRs"
                        hint={periodLabel}
                        icon="throughput"
                        metric={kpis.throughput}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Lead time"
                        hint="First commit → merged"
                        icon="leadTime"
                        metric={kpis.leadTime}
                    />
                    <MetricTile
                        span="1/6"
                        size="lg"
                        label="Open PRs"
                        hint="Right now"
                        icon="openPullRequests"
                        metric={kpis.openPullRequests}
                        footer={
                            <div className="flex flex-wrap items-center gap-1.5">
                                {(kpis.stalePullRequests.value ?? 0) > 0 ? (
                                    <Badge tone="warn">
                                        {kpis.stalePullRequests.value} stale
                                    </Badge>
                                ) : (
                                    <Badge tone="good">None stale</Badge>
                                )}
                                <BenchmarkBadge
                                    metric={kpis.stalePullRequests}
                                    label="Stale PRs"
                                    hint="Open without recent activity"
                                />
                            </div>
                        }
                    />
                </Headline>

                <PanelCell
                    span="7/12"
                    title="Where the time goes"
                    description={`Average per PR merged in the ${periodLabel}, from first commit to merge`}
                >
                    <StageBar stages={report.cycleBreakdown} />
                </PanelCell>
                <ChartPanel
                    span="5/12"
                    title="Time to merge distribution"
                    description="How many PRs landed within each window"
                    chart={{
                        kind: 'bar',
                        height: 150,
                        labels: report.cycleDistribution.map(
                            (bucket) => bucket.label,
                        ),
                        series: [
                            {
                                key: 'count',
                                label: 'Pull requests',
                                color: 'var(--series-1)',
                                values: report.cycleDistribution.map(
                                    (bucket) => bucket.count,
                                ),
                            },
                        ],
                        format: (value) => formatNumber(value),
                    }}
                />
            </Grid>

            <p className="mt-3 text-xs text-ink-muted">
                Pull requests by the team{' '}
                {repository
                    ? `in ${repository}`
                    : 'across every watched repository'}
                . Everything that happens to one counts for the team its author
                was on at the time. Durations count working time:{' '}
                {report.workingTime}.
            </p>

            <Section title="Trends" description={`Per ${period.granularity}`}>
                <ChartPanel
                    title={trends.throughput.title}
                    metric={trends.throughput}
                    description={trends.throughput.summary}
                    chart={charts.throughput}
                />
                <ChartPanel
                    title={trends.timeToMerge.title}
                    metric={trends.timeToMerge}
                    description={trends.timeToMerge.summary}
                    chart={charts.timeToMerge}
                />
                <ChartPanel
                    title={trends.pickupTime.title}
                    metric={trends.pickupTime}
                    description={trends.pickupTime.summary}
                    chart={charts.pickupTime}
                />
                <ChartPanel
                    title={trends.openPullRequests.title}
                    metric={trends.openPullRequests}
                    description={trends.openPullRequests.summary}
                    chart={charts.openPullRequests}
                />
            </Section>

            <Section
                title="Flow & review quality"
                description={`Compared with ${comparedWith} · pick a metric to see it over time`}
            >
                <FlowAndReviewQuality
                    report={report}
                    defaultBranch={null}
                    labels={labels}
                    tooltipLabels={tooltipLabels}
                    dates={dates}
                />
            </Section>

            <Section title="Work in progress">
                <Cell>
                    <WorkInProgress
                        pullRequests={report.workInProgress}
                        showRepository
                    />
                </Cell>
                <Cell>
                    <Remediations
                        remediations={report.remediations}
                        showRepository
                    />
                </Cell>
            </Section>

            <Section title="People" description={periodLabel}>
                <ActiveDevelopers
                    report={report}
                    labels={labels}
                    tooltipLabels={tooltipLabels}
                    dates={dates}
                />
            </Section>

            <Section
                title="Slowest merges"
                description="The PRs that dragged the tail out"
            >
                <Cell>
                    <SlowestMerged
                        pullRequests={report.slowestMerged}
                        showRepository
                    />
                </Cell>
            </Section>
        </>
    );
}
