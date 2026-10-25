import { Head, usePoll } from '@inertiajs/react';
import { Sparkline } from '@/components/charts/sparkline';
import { BenchmarkBadge, MetricLabel } from '@/components/metric-card';
import { Avatar } from '@/components/ui/avatar';
import {
    BranchPill,
    WaitingList,
    WALL_BADGE,
    WallDelta,
    WallFooter,
    WallStageBar,
} from '@/components/wall';
import { useWallRotation } from '@/hooks/use-wall-rotation';
import { formatMetric } from '@/lib/format';
import { ReportPeriod } from '@/lib/periods';
import { repositoryCard, viewMetrics } from '@/lib/repository-views';
import type {
    RepositoryCardView,
    RepositoryView,
    SummaryMetric,
} from '@/lib/repository-views';
import { cn } from '@/lib/utils';
import type {
    CiFailureRateScope,
    CycleStage,
    Metric,
    MetricKey,
    OpenPullRequest,
    TrendPoint,
    DefaultBranchStatus,
    Delivery,
} from '@/types';

type Slide = {
    id: number;
    owner: string;
    name: string;
    fullName: string;
    avatarUrl: string | null;
    defaultBranch: string | null;
    view: RepositoryView;
    cardView: RepositoryCardView;
    ciFailureRateScope: CiFailureRateScope;
    sync: { status: string; hasData: boolean; finishedAt: string | null };
    kpis: Record<MetricKey, Metric>;
    trend: TrendPoint[];
    cycleBreakdown: CycleStage[];
    defaultBranchStatus: DefaultBranchStatus;
    waitingForReview: OpenPullRequest[];
    delivery: Delivery;
    workingTime: string;
};

type Props = {
    days: number;
    rotateSeconds: number;
    repositories: Slide[];
};

const STALE_AFTER_MS = 60 * 60 * 1000;

/** The most repositories an overview page fits before the cards get too cramped to read. */
const OVERVIEW_PAGE_SIZE = 8;

type SlideId = number | `overview-${number}`;

/** Splits the repositories into evenly filled overview pages, e.g. 10 becomes 5 + 5 rather than 8 + 2. */
function overviewPages(repositories: Slide[]): Slide[][] {
    const count = Math.ceil(repositories.length / OVERVIEW_PAGE_SIZE);
    const size = Math.ceil(repositories.length / Math.max(1, count));

    return Array.from({ length: count }, (_, page) =>
        repositories.slice(page * size, (page + 1) * size),
    );
}

export default function Wall({ days, rotateSeconds, repositories }: Props) {
    usePoll(60_000, { only: ['repositories'] });

    const pages = repositories.length > 1 ? overviewPages(repositories) : [];
    const slideIds: SlideId[] = [
        ...pages.map((_, page): SlideId => `overview-${page}`),
        ...repositories.map((repository) => repository.id),
    ];

    const { current, index, show, paused, progress, now } = useWallRotation(
        slideIds,
        rotateSeconds,
    );
    const slide = repositories.find((repository) => repository.id === current);
    const page =
        typeof current === 'string'
            ? Number(current.slice('overview-'.length))
            : 0;

    return (
        <div className="flex min-h-dvh flex-col bg-page px-4 pt-4 text-ink wall:h-screen wall:overflow-hidden wall:px-[3vw] wall:pt-[3vh]">
            <Head title="Wall" />

            <ReportPeriod value={{ days, from: null, to: null, all: false }}>
                <div className="flex-1 pb-4 wall:min-h-0 wall:pb-0">
                    {repositories.length === 0 ? (
                        <div className="flex h-full items-center justify-center text-lg text-ink-muted wall:text-[3vh]">
                            Nothing on the big board yet: no repositories are
                            being watched.
                        </div>
                    ) : !slide ? (
                        <Overview
                            repositories={pages[page] ?? repositories}
                            page={page}
                            pageCount={pages.length}
                            days={days}
                        />
                    ) : (
                        <RepositorySlide
                            key={slide.id}
                            slide={slide}
                            days={days}
                        />
                    )}
                </div>
            </ReportPeriod>

            <Footer
                repositories={repositories}
                slideIds={slideIds}
                index={index}
                progress={progress}
                paused={paused}
                now={now}
                onSelect={show}
            />
        </div>
    );
}

function Overview({
    repositories,
    page,
    pageCount,
    days,
}: {
    repositories: Slide[];
    page: number;
    pageCount: number;
    days: number;
}) {
    return (
        <div className="flex h-full flex-col">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                <h1 className="text-3xl font-semibold tracking-tight wall:text-[5vh]">
                    Delivery velocity
                </h1>
                <span className="text-base text-ink-muted wall:text-[2.2vh]">
                    Last {days} days vs the {days} before
                    {pageCount > 1 && ` · ${page + 1} of ${pageCount}`}
                </span>
            </div>
            <div
                className={cn(
                    'mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 wall:mt-[3vh] wall:min-h-0 wall:flex-1 wall:auto-rows-fr wall:gap-[2vh]',
                    repositories.length > 6
                        ? 'wall:grid-cols-4 wall:grid-rows-2'
                        : repositories.length > 4 && 'wall:grid-cols-3',
                )}
            >
                {repositories.map((repository) => (
                    <div
                        key={repository.id}
                        className={cn(
                            'flex min-h-0 flex-col rounded-xl border bg-surface p-4 wall:rounded-[1.5vh] wall:p-[2.5vh]',
                            repository.defaultBranchStatus.state === 'failing'
                                ? 'border-bad/60'
                                : 'border-line',
                        )}
                    >
                        <div className="flex items-center justify-between gap-3 wall:gap-[1vh]">
                            <div className="flex min-w-0 items-center gap-3 wall:gap-[1.2vh]">
                                <Avatar
                                    src={repository.avatarUrl}
                                    login={repository.owner}
                                    size={36}
                                    className="size-9 rounded-lg wall:size-[4vh] wall:rounded-[0.8vh]"
                                />
                                <span className="truncate text-xl font-semibold tracking-tight wall:text-[3vh]">
                                    {repository.name}
                                </span>
                            </div>
                            <BranchPill
                                status={repository.defaultBranchStatus}
                                branch={repository.defaultBranch}
                                compact
                            />
                        </div>
                        <div className="mt-4 flex min-h-0 flex-1 flex-col wall:mt-[2vh]">
                            <div className="text-sm text-ink-muted wall:text-[1.6vh]">
                                Merged per {days > 30 ? 'week' : 'day'}
                            </div>
                            <div className="mt-2 h-20 min-h-0 wall:mt-[0.8vh] wall:h-auto wall:flex-1">
                                <Sparkline
                                    fill
                                    bars
                                    values={repository.trend.map(
                                        (point) => point.merged,
                                    )}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4 pt-4 sm:grid-cols-4 wall:gap-[1.5vh] wall:pt-[2vh]">
                            {repositoryCard(repository.cardView).wallTile.map(
                                ({ metric, label }) => (
                                    <OverviewNumber
                                        key={metric}
                                        label={label}
                                        metric={repository.kpis[metric]}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function OverviewNumber({ label, metric }: { label: string; metric: Metric }) {
    return (
        <div className="min-w-0">
            <div className="truncate text-sm text-ink-muted wall:text-[1.7vh]">
                <MetricLabel metric={metric}>{label}</MetricLabel>
            </div>
            <div className="text-2xl leading-tight font-semibold tracking-tight whitespace-nowrap wall:text-[3.6vh]">
                {formatMetric(metric.value, metric.unit, true)}
            </div>
            <div className="flex flex-wrap items-center gap-1.5 wall:gap-[0.6vh]">
                <WallDelta metric={metric} />
                <BenchmarkBadge
                    metric={metric}
                    label={label}
                    className={WALL_BADGE}
                />
            </div>
        </div>
    );
}

function RepositorySlide({ slide, days }: { slide: Slide; days: number }) {
    const { kpis } = slide;

    if (!slide.sync.hasData) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-3 text-center wall:gap-[2vh]">
                <div className="text-3xl font-semibold break-all wall:text-[6vh]">
                    {slide.fullName}
                </div>
                <div className="text-lg text-ink-muted wall:text-[2.5vh]">
                    Fetching history from GitHub…
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <div className="flex flex-col gap-4 wall:flex-row wall:items-center wall:justify-between wall:gap-[2vw]">
                <div className="flex min-w-0 items-center gap-3 wall:gap-[1.5vh]">
                    <Avatar
                        src={slide.avatarUrl}
                        login={slide.owner}
                        size={56}
                        className="size-12 rounded-xl wall:size-[6vh] wall:rounded-[1.2vh]"
                    />
                    <div className="min-w-0">
                        <div className="truncate text-sm text-ink-muted wall:text-[2vh]">
                            {slide.owner} · last {days} days
                        </div>
                        <h1 className="truncate text-3xl leading-tight font-semibold tracking-tight wall:text-[5vh]">
                            {slide.name}
                        </h1>
                    </div>
                </div>
                <div className="flex max-w-full min-w-0 flex-col items-start gap-3 wall:max-w-[55%] wall:items-end wall:gap-[1vh]">
                    <BranchPill
                        status={slide.defaultBranchStatus}
                        branch={slide.defaultBranch}
                    />
                    <DeliveryLine slide={slide} />
                </div>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 wall:mt-[3vh] wall:gap-[2vh]">
                {viewMetrics(slide.view).wallSlide.map(({ metric, label }) => (
                    <BigNumber
                        key={metric}
                        label={label}
                        metric={kpis[metric]}
                        {...bigNumberDetails(slide, metric)}
                    />
                ))}
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 wall:mt-[2vh] wall:min-h-0 wall:flex-1 wall:grid-cols-12 wall:gap-[2vh] wall:pb-[2vh]">
                <Tile title="Where the time goes" className="wall:col-span-5">
                    <WallStageBar stages={slide.cycleBreakdown} />
                </Tile>
                <Tile
                    title="Waiting for review"
                    aside={
                        (kpis.stalePullRequests.value ?? 0) > 0
                            ? `${kpis.stalePullRequests.value} stale · ${kpis.openPullRequests.value} open`
                            : `${kpis.openPullRequests.value} open`
                    }
                    className="wall:col-span-7"
                >
                    <WaitingList pullRequests={slide.waitingForReview} />
                </Tile>
            </div>
        </div>
    );
}

/** The hint, trend and colour each metric's big number on a repository slide comes with. */
function bigNumberDetails(
    slide: Slide,
    metric: SummaryMetric,
): { hint: string; series: (number | null)[]; color?: string } {
    const { kpis, trend, delivery } = slide;
    const branch = slide.defaultBranch ?? 'main';

    switch (metric) {
        case 'cycleTime':
            return {
                hint: 'median, opened → merged',
                series: trend.map((point) => point.cycleTime),
            };
        case 'pickupTime':
            return {
                hint: 'median wait for a first response',
                series: trend.map((point) => point.pickupTime),
                color: 'var(--series-2)',
            };
        case 'throughput':
            return {
                hint: `${kpis.opened.value ?? 0} opened`,
                series: trend.map((point) => point.merged),
            };
        case 'ciFailureRate':
            return {
                hint:
                    slide.ciFailureRateScope === 'default_branch'
                        ? `on ${branch}`
                        : `${formatMetric(kpis.ciDefaultBranchFailureRate.value, 'ratio')} on ${branch}`,
                series: trend.map((point) => point.ciFailureRate),
                color: 'var(--series-3)',
            };
        case 'deployments':
            return {
                hint:
                    delivery.pending.count > 0
                        ? `${delivery.pending.count} merged, not shipped yet`
                        : 'everything merged has shipped',
                series: trend.map((point) => point.deployments),
                color: 'var(--series-4)',
            };
        case 'changeFailureRate':
            return {
                hint: 'shipped changes that needed a fix',
                series: [],
                color: 'var(--series-3)',
            };
        case 'opened':
            return {
                hint: `${kpis.throughput.value ?? 0} merged`,
                series: trend.map((point) => point.opened),
                color: 'var(--series-2)',
            };
        case 'openPullRequests':
            return {
                hint: `${kpis.stalePullRequests.value ?? 0} stale`,
                series: trend.map((point) => point.open),
            };
    }
}

function BigNumber({
    label,
    hint,
    metric,
    series,
    color = 'var(--series-1)',
}: {
    label: string;
    hint: string;
    metric: Metric;
    series: (number | null)[];
    color?: string;
}) {
    return (
        <div className="flex min-w-0 flex-col rounded-xl border border-line bg-surface p-4 wall:rounded-[1.5vh] wall:p-[2.2vh]">
            <div className="flex items-start justify-between gap-3 wall:gap-[1vh]">
                <div className="min-w-0">
                    <div className="text-lg font-medium wall:text-[2.3vh]">
                        <MetricLabel metric={metric}>{label}</MetricLabel>
                    </div>
                    <div className="truncate text-sm text-ink-muted wall:text-[1.6vh]">
                        {hint}
                    </div>
                </div>
                <WallDelta metric={metric} />
            </div>
            <div className="mt-3 flex flex-wrap items-end gap-x-3 gap-y-2 wall:mt-[1vh] wall:flex-nowrap wall:gap-[1.2vh]">
                <span className="text-5xl leading-none font-semibold tracking-tighter whitespace-nowrap wall:text-[9vh]">
                    {formatMetric(metric.value, metric.unit)}
                </span>
                <BenchmarkBadge
                    metric={metric}
                    label={label}
                    hint={hint}
                    className={cn(WALL_BADGE, 'mb-1 wall:mb-[1vh]')}
                />
            </div>
            <div className="mt-4 wall:mt-[1.5vh]">
                <Sparkline
                    values={series.filter(
                        (value): value is number => value !== null,
                    )}
                    color={color}
                    height={56}
                />
            </div>
        </div>
    );
}

function DeliveryLine({ slide }: { slide: Slide }) {
    const { delivery, kpis } = slide;

    if (delivery.source === null) {
        return null;
    }

    const noun = delivery.source === 'releases' ? 'release' : 'deploy';
    const count = kpis.deployments.value ?? 0;

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-base text-ink-secondary wall:flex-nowrap wall:gap-[1.4vh] wall:text-[2vh]">
            <span>
                <span className="font-semibold text-ink">{count}</span> {noun}
                {count === 1 ? '' : 's'}
            </span>
            {kpis.changeFailureRate.value !== null && (
                <span>
                    <span className="font-semibold text-ink">
                        {formatMetric(kpis.changeFailureRate.value, 'ratio')}
                    </span>{' '}
                    needed a fix{' '}
                    <BenchmarkBadge
                        metric={kpis.changeFailureRate}
                        label="Change failure rate"
                        hint="Deploys followed by a revert or hotfix"
                        className={WALL_BADGE}
                    />
                </span>
            )}
            {delivery.pending.count > 0 && (
                <span className="text-warn">
                    {delivery.pending.count} merged, not shipped
                </span>
            )}
        </div>
    );
}

function Tile({
    title,
    aside,
    className,
    children,
}: {
    title: string;
    aside?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex min-h-0 min-w-0 flex-col rounded-xl border border-line bg-surface p-4 wall:rounded-[1.5vh] wall:p-[2.2vh]',
                className,
            )}
        >
            <div className="flex items-baseline justify-between gap-3 wall:gap-[1vh]">
                <h2 className="text-lg font-medium wall:text-[2.3vh]">
                    {title}
                </h2>
                {aside && (
                    <span className="text-right text-sm text-ink-muted wall:text-[1.7vh]">
                        {aside}
                    </span>
                )}
            </div>
            <div className="mt-4 min-h-0 flex-1 wall:mt-[2vh]">{children}</div>
        </div>
    );
}

function Footer({
    repositories,
    slideIds,
    index,
    progress,
    paused,
    now,
    onSelect,
}: {
    repositories: Slide[];
    slideIds: SlideId[];
    index: number;
    progress: number;
    paused: boolean;
    now: number;
    onSelect: (id: SlideId) => void;
}) {
    const stale = repositories.filter(
        (repository) =>
            repository.sync.status === 'failed' ||
            !repository.sync.finishedAt ||
            now - new Date(repository.sync.finishedAt).getTime() >
                STALE_AFTER_MS,
    );

    return (
        <WallFooter
            slides={slideIds.map((id) => ({
                key: id,
                label:
                    typeof id === 'string'
                        ? `overview ${Number(id.slice('overview-'.length)) + 1}`
                        : (repositories.find(
                              (repository) => repository.id === id,
                          )?.name ?? ''),
            }))}
            index={index}
            progress={progress}
            paused={paused}
            now={now}
            onSelect={(position) => onSelect(slideIds[position])}
            warning={
                stale.length > 0
                    ? `Data may be stale: ${stale.map((repository) => repository.name).join(', ')}`
                    : null
            }
        />
    );
}
