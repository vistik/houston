import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertIcon,
    CheckIcon,
    RefreshIcon,
    TrashIcon,
} from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import type { ConfirmVisit } from '@/components/ui/confirm-dialog';
import { Tooltip } from '@/components/ui/hover-card';
import { Panel } from '@/components/ui/panel';
import { Select } from '@/components/ui/select';
import { useNow } from '@/hooks/use-now';
import { usePollWhile } from '@/hooks/use-poll-while';
import SettingsLayout from '@/layouts/settings-layout';
import {
    formatDate,
    formatDuration,
    formatNumber,
    formatPercent,
    timeAgo,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    destroy,
    lookback as updateLookback,
    show,
    sync as syncRepository,
} from '@/routes/repositories';
import { store as syncAll } from '@/routes/settings/syncs';
import type {
    RateLimit,
    RateLimits,
    RepositorySyncOverview,
    SyncPhase,
    SyncProgress,
} from '@/types';
import { buttonClass } from '@/components/ui/button';

type Lookback = {
    defaultDays: number;
    options: number[];
};

type Props = {
    staleAfterMinutes: number;
    defaultLookbackDays: number;
    lookbackOptions: number[];
    rateLimits: RateLimits;
    repositories: RepositorySyncOverview[];
};

export default function SyncsIndex({
    staleAfterMinutes,
    defaultLookbackDays,
    lookbackOptions,
    rateLimits,
    repositories,
}: Props) {
    const lookback = {
        defaultDays: defaultLookbackDays,
        options: lookbackOptions,
    };
    const active = repositories.filter(
        (repository) => repository.sync.inProgress,
    );
    const now = useNow();

    usePollWhile(active.length > 0, ['repositories', 'rateLimits'], 2000);

    return (
        <SettingsLayout>
            <Head title="Syncs" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-ink-secondary">
                        What Houston is pulling from GitHub right now, and how
                        long it has left. Repositories refresh when viewed after{' '}
                        {staleAfterMinutes} minutes.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() =>
                        router.post(syncAll().url, {}, { preserveScroll: true })
                    }
                    disabled={repositories.length === active.length}
                    className={buttonClass({
                        variant: 'secondary',
                        size: 'sm',
                    })}
                >
                    <RefreshIcon width={13} height={13} />
                    Sync all
                </button>
            </div>

            <section className="mt-6 rounded-xl border border-line bg-surface shadow-[0_1px_2px_rgba(0,0,0,0.03)]">
                <RateLimitsSummary rateLimits={rateLimits} now={now} />
            </section>

            <Panel className="mt-6" bodyClassName="p-0">
                {repositories.length === 0 ? (
                    <p className="p-8 text-center text-sm text-ink-muted">
                        Nothing on the launch pad: no repositories are being
                        watched yet.
                    </p>
                ) : (
                    <ul className="divide-y divide-line">
                        {repositories.map((repository) => (
                            <SyncRow
                                key={repository.id}
                                repository={repository}
                                lookback={lookback}
                                now={now}
                            />
                        ))}
                    </ul>
                )}
            </Panel>
        </SettingsLayout>
    );
}

function remaining(finishAt: number, now: number): string {
    const seconds = (finishAt - now) / 1000;

    return seconds < 5 ? 'Any moment' : `~${formatDuration(seconds)}`;
}

/** Stored as the lookback to cache the whole history of a repository. */
const AllTime = 0;

function lookbackLabel(days: number): string {
    if (days === AllTime) {
        return 'All time';
    }

    if (days % 365 === 0) {
        return days === 365 ? '1 year' : `${days / 365} years`;
    }

    return `${days} days`;
}

function historyLabel(days: number): string {
    return days === AllTime
        ? 'the whole history'
        : `${lookbackLabel(days)} of history`;
}

/**
 * Picks how far back a repository's history is cached. Reaching further back than the cache does
 * fetches the older history, so that asks first.
 */
function LookbackSelect({
    repository,
    lookback,
}: {
    repository: RepositorySyncOverview;
    lookback: Lookback;
}) {
    // The lookback waiting on a confirmation that fetching its older history is fine; undefined when none is.
    const [pending, setPending] = useState<number | null | undefined>(
        undefined,
    );

    const save = (days: number | null, visit?: ConfirmVisit) =>
        router.put(
            updateLookback(repository.id).url,
            { lookback_days: days },
            { preserveScroll: true, ...visit },
        );

    const change = (value: string) => {
        const days = value === '' ? null : Number(value);
        const reachesFrom =
            days === AllTime
                ? -Infinity
                : Date.now() - (days ?? lookback.defaultDays) * 86_400_000;
        const historyFrom = repository.sync.historyFrom
            ? new Date(repository.sync.historyFrom).getTime()
            : null;

        if (historyFrom !== null && reachesFrom < historyFrom - 86_400_000) {
            setPending(days);

            return;
        }

        save(days);
    };

    return (
        <>
            <ConfirmDialog
                open={pending !== undefined}
                onClose={() => setPending(undefined)}
                onConfirm={(visit) => save(pending ?? null, visit)}
                title={`Fetch ${historyLabel(pending ?? lookback.defaultDays)}?`}
                description={`Houston fetches the older history of ${repository.fullName} from GitHub. It can take a while; the current reports stay available meanwhile.`}
                confirmLabel="Fetch history"
                processingLabel="Saving…"
            />
            <label className="flex items-center gap-1.5 text-xs text-ink-muted">
                <span className="hidden sm:inline">History</span>
                <Select
                    label={`How far back to sync ${repository.fullName}`}
                    value={String(repository.lookbackDays ?? '')}
                    onChange={change}
                    className="h-7 font-normal"
                    options={[
                        {
                            value: '',
                            label: `Default (${lookbackLabel(lookback.defaultDays)})`,
                        },
                        ...lookback.options.map((days) => ({
                            value: String(days),
                            label: lookbackLabel(days),
                        })),
                    ]}
                />
            </label>
        </>
    );
}

function SyncRow({
    repository,
    lookback,
    now,
}: {
    repository: RepositorySyncOverview;
    lookback: Lookback;
    now: number;
}) {
    const { sync } = repository;
    const running = sync.inProgress && sync.status === 'running';
    const queued = sync.inProgress && sync.status === 'queued';
    const [confirmingStop, setConfirmingStop] = useState(false);

    return (
        <li className="flex flex-col gap-4 px-5 py-4">
            <div className="flex items-start justify-between gap-4">
                <Link
                    href={show({
                        owner: repository.owner,
                        name: repository.name,
                    })}
                    className="group flex min-w-0 items-center gap-2.5"
                >
                    <Avatar
                        src={repository.avatarUrl}
                        login={repository.owner}
                        size={28}
                        className="rounded-md"
                    />
                    <div className="min-w-0">
                        <div className="truncate text-xs text-ink-muted">
                            {repository.owner}
                        </div>
                        <div className="truncate font-semibold tracking-tight group-hover:text-accent">
                            {repository.name}
                        </div>
                    </div>
                </Link>
                <div className="flex shrink-0 items-center gap-3">
                    <LookbackSelect
                        repository={repository}
                        lookback={lookback}
                    />
                    <StatusBadge sync={sync} />
                    <Tooltip content="Sync now">
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    syncRepository(repository.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            disabled={sync.inProgress}
                            className="inline-flex size-7 items-center justify-center rounded-lg border border-line bg-surface text-ink-secondary transition hover:text-ink disabled:opacity-50"
                            aria-label={`Sync ${repository.fullName}`}
                        >
                            <RefreshIcon
                                width={14}
                                height={14}
                                className={cn(running && 'animate-spin')}
                            />
                        </button>
                    </Tooltip>
                    <ConfirmDialog
                        open={confirmingStop}
                        onClose={() => setConfirmingStop(false)}
                        onConfirm={(visit) =>
                            router.delete(destroy.url(repository.id), {
                                preserveScroll: true,
                                ...visit,
                            })
                        }
                        title={`Stop watching ${repository.fullName}?`}
                        description="Its cached pull requests, reviews and CI runs will be removed. You can watch it again later."
                        confirmLabel="Stop watching"
                        processingLabel="Removing…"
                        destructive
                    />
                    <Tooltip content="Stop watching">
                        <button
                            type="button"
                            onClick={() => setConfirmingStop(true)}
                            className="inline-flex size-7 items-center justify-center rounded-lg border border-line bg-surface text-ink-secondary transition hover:border-bad/30 hover:bg-bad-soft hover:text-bad"
                            aria-label={`Stop watching ${repository.fullName}`}
                        >
                            <TrashIcon width={14} height={14} />
                        </button>
                    </Tooltip>
                </div>
            </div>

            {running ? (
                <RunningDetails sync={sync} now={now} />
            ) : sync.rateLimitedUntil ? (
                <div className="flex flex-col gap-3">
                    <Phases phases={sync.phases} />
                    <p className="text-xs text-ink-muted">
                        Paused for GitHub&apos;s rate limit
                        {new Date(sync.rateLimitedUntil).getTime() > now
                            ? `, resumes in ${formatDuration((new Date(sync.rateLimitedUntil).getTime() - now) / 1000)}`
                            : ', resuming…'}
                        {' · '}picks up where it stopped
                    </p>
                </div>
            ) : queued ? (
                <p className="text-xs text-ink-muted">
                    Waiting for a queue worker
                    {repository.queuePosition !== null &&
                        ` · #${repository.queuePosition} in line`}
                    {' · '}queued {timeAgo(sync.queuedAt)}
                    {sync.estimatedFinishAt &&
                        ` · last sync took ${formatDuration(sync.lastDurationSeconds)}`}
                    {!sync.estimatedFinishAt &&
                        sync.backfill &&
                        ` · will fetch ${historyLabel(repository.lookbackDays ?? lookback.defaultDays)}, which can take a while`}
                </p>
            ) : sync.status === 'failed' ? (
                <div className="flex flex-col gap-3">
                    <Phases phases={sync.phases} />
                    <p className="flex items-start gap-1.5 text-xs text-bad">
                        <AlertIcon
                            width={13}
                            height={13}
                            className="mt-px shrink-0"
                        />
                        <span>
                            {failedPhase(sync) &&
                                `Failed while fetching ${failedPhase(sync)!.label.toLowerCase()} ${timeAgo(sync.finishedAt)}: `}
                            {sync.error ?? 'Unknown error'}
                        </span>
                    </p>
                </div>
            ) : (
                <p className="text-xs text-ink-muted">
                    {sync.finishedAt
                        ? `Synced ${timeAgo(sync.finishedAt)}`
                        : 'Never synced'}
                    {sync.lastDurationSeconds !== null &&
                        ` · took ${formatDuration(sync.lastDurationSeconds)}`}
                    {sync.historyFrom &&
                        ` · history since ${formatDate(sync.historyFrom, true)}`}
                </p>
            )}
        </li>
    );
}

function failedPhase(sync: SyncProgress): SyncPhase | undefined {
    return sync.phases.find((phase) => phase.state === 'failed');
}

function RateLimitsSummary({
    rateLimits,
    now,
}: {
    rateLimits: RateLimits;
    now: number;
}) {
    return (
        <div className="min-w-0 flex-1 px-5 py-3">
            <div
                className="flex items-baseline justify-between gap-3 text-xs text-ink-muted"
                title={`Syncs pause when ${formatNumber(rateLimits.reserve)} or fewer are left and pick up where they stopped once the limit resets.`}
            >
                <span className="truncate">GitHub rate limits</span>
                {rateLimits.observedAt && (
                    <span className="text-[11px] whitespace-nowrap">
                        Checked {timeAgo(rateLimits.observedAt)}
                    </span>
                )}
            </div>
            {rateLimits.resources.length === 0 ? (
                <p className="mt-1.5 text-xs text-ink-muted">
                    {rateLimits.error
                        ? `Could not check the rate limits: ${rateLimits.error}`
                        : 'No rate limits seen yet.'}
                </p>
            ) : (
                <div className="mt-1.5 grid gap-x-6 gap-y-2.5 sm:grid-cols-2">
                    {rateLimits.resources.map((limit) => (
                        <RateLimitMeter
                            key={limit.resource}
                            limit={limit}
                            reserve={rateLimits.reserve}
                            now={now}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function RateLimitMeter({
    limit,
    reserve,
    now,
}: {
    limit: RateLimit;
    reserve: number;
    now: number;
}) {
    const share = limit.limit > 0 ? limit.remaining / limit.limit : 0;
    const resetsIn = (new Date(limit.resetAt).getTime() - now) / 1000;
    const pausedFor = limit.blockedUntil
        ? Math.max((new Date(limit.blockedUntil).getTime() - now) / 1000, 0)
        : null;
    // With budget left, GitHub refused a request for going too fast (a secondary limit).
    const pauseReason =
        limit.remaining <= reserve
            ? `Paused to keep the last ${formatNumber(reserve)} requests in reserve until the limit resets.`
            : 'GitHub asked Houston to slow down (a secondary rate limit), although requests are left.';

    return (
        <div>
            <div className="flex items-baseline justify-between gap-3 text-xs">
                <span className="min-w-0 truncate">
                    <span className="font-medium text-ink-secondary">
                        {limit.label}
                    </span>
                    <span
                        className={cn(
                            'text-[11px]',
                            limit.blockedUntil ? 'text-bad' : 'text-ink-muted',
                        )}
                        title={pausedFor !== null ? pauseReason : undefined}
                    >
                        {' · '}
                        {pausedFor !== null
                            ? `Syncs paused · resumes ${pausedFor > 5 ? `in ${formatDuration(pausedFor)}` : 'any moment'}`
                            : resetsIn > 0
                              ? `Resets in ${formatDuration(resetsIn)}`
                              : 'Reset, refreshing…'}
                    </span>
                </span>
                <span className="whitespace-nowrap text-ink-muted tabular-nums">
                    <span className="font-medium text-ink">
                        {formatNumber(limit.remaining)}
                    </span>{' '}
                    of {formatNumber(limit.limit)} left
                </span>
            </div>
            <div
                className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line"
                role="meter"
                aria-label={`${limit.label} requests left`}
                aria-valuemin={0}
                aria-valuemax={limit.limit}
                aria-valuenow={limit.remaining}
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-[width] duration-700',
                        limit.blockedUntil
                            ? 'bg-bad'
                            : share < 0.2
                              ? 'bg-warn'
                              : 'bg-good',
                    )}
                    style={{ width: `${Math.max(share, 0.01) * 100}%` }}
                />
            </div>
        </div>
    );
}

function StatusBadge({ sync }: { sync: SyncProgress }) {
    if (sync.inProgress && sync.status === 'running') {
        return sync.stalled ? (
            <Badge tone="warn">Stalled</Badge>
        ) : (
            <Badge tone="accent">
                {sync.backfill ? 'Backfilling' : 'Syncing'}
            </Badge>
        );
    }

    if (sync.rateLimitedUntil) {
        return <Badge tone="warn">Rate limited</Badge>;
    }

    if (sync.inProgress) {
        return <Badge>Queued</Badge>;
    }

    if (sync.status === 'failed') {
        return (
            <Badge tone="bad">
                <AlertIcon width={11} height={11} /> Failed
            </Badge>
        );
    }

    if (sync.status === 'succeeded') {
        return (
            <Badge tone="good">
                <CheckIcon width={11} height={11} /> Up to date
            </Badge>
        );
    }

    return <Badge>Not synced</Badge>;
}

function RunningDetails({ sync, now }: { sync: SyncProgress; now: number }) {
    const elapsed = sync.startedAt
        ? (now - new Date(sync.startedAt).getTime()) / 1000
        : null;
    const facts = [
        `${formatNumber(sync.pullRequestsFetched)} pull requests`,
        `${formatNumber(sync.workflowRunsFetched)} workflow runs`,
        elapsed !== null && `running for ${formatDuration(elapsed)}`,
    ].filter(Boolean);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-3">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-line">
                    <div
                        className={cn(
                            'h-full rounded-full transition-[width] duration-700',
                            sync.stalled ? 'bg-warn' : 'bg-accent',
                        )}
                        style={{ width: `${(sync.progress ?? 0) * 100}%` }}
                    />
                </div>
                <span className="w-10 text-right text-xs font-medium text-ink-secondary tabular-nums">
                    {formatPercent(sync.progress)}
                </span>
                <span className="w-24 text-right text-xs font-medium tabular-nums">
                    {sync.estimatedFinishAt
                        ? `${remaining(new Date(sync.estimatedFinishAt).getTime(), now)} left`
                        : 'Estimating…'}
                </span>
            </div>

            <Phases phases={sync.phases} />

            <p className="text-xs text-ink-muted">
                {facts.join(' · ')}
                {sync.stalled &&
                    ` · no progress since ${timeAgo(sync.lastActivityAt)}, GitHub may be slow or the worker may have stopped`}
            </p>
        </div>
    );
}

function Phases({ phases }: { phases: SyncPhase[] }) {
    return (
        <ol className="grid grid-cols-2 gap-2 sm:grid-cols-5">
            {phases
                .filter((phase) => phase.state !== 'skipped')
                .map((phase) => (
                    <li key={phase.key} className="min-w-0">
                        <Tooltip
                            className="flex w-full min-w-0 flex-col"
                            content={
                                <>
                                    <div className="font-medium">
                                        {phase.label}
                                    </div>
                                    <div className="text-ink-muted">
                                        {phaseStatus(phase)}
                                    </div>
                                </>
                            }
                        >
                            <div className="h-1 overflow-hidden rounded-full bg-line">
                                <div
                                    className={cn(
                                        'h-full rounded-full',
                                        phase.state === 'done' &&
                                            'w-full bg-good',
                                        phase.state === 'failed' && 'bg-bad',
                                        phase.state === 'active' &&
                                            phase.progress === null &&
                                            'w-full animate-pulse bg-accent/60',
                                        phase.state === 'active' &&
                                            phase.progress !== null &&
                                            'bg-accent transition-[width] duration-700',
                                    )}
                                    style={
                                        (phase.state === 'active' &&
                                            phase.progress !== null) ||
                                        phase.state === 'failed'
                                            ? {
                                                  width: `${Math.max(phase.progress ?? 1, 0.02) * 100}%`,
                                              }
                                            : undefined
                                    }
                                />
                            </div>
                            <div
                                className={cn(
                                    'mt-1.5 truncate text-[11px]',
                                    phase.state === 'active'
                                        ? 'font-medium text-ink'
                                        : phase.state === 'failed'
                                          ? 'text-bad'
                                          : 'text-ink-muted',
                                )}
                            >
                                {phase.label}
                                {phase.state === 'active' &&
                                    phase.progress !== null &&
                                    ` · ${formatPercent(phase.progress)}`}
                            </div>
                        </Tooltip>
                    </li>
                ))}
        </ol>
    );
}

function phaseStatus(phase: SyncPhase): string {
    switch (phase.state) {
        case 'done':
            return 'Done';
        case 'failed':
            return 'Failed';
        case 'active':
            return [
                'In progress',
                phase.progress !== null && formatPercent(phase.progress),
                phase.startedAt && `started ${timeAgo(phase.startedAt)}`,
            ]
                .filter(Boolean)
                .join(' · ');
        default:
            return 'Waiting';
    }
}
