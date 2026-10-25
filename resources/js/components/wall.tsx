import { STAGE_COLORS } from '@/components/charts/stage-bar';
import { LogoMark } from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import { formatDurationCompact, metricDelta, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    CycleStage,
    DefaultBranchStatus,
    Metric,
    OpenPullRequest,
} from '@/types';

/*
 * Building blocks shared by the wall displays. Their `wall:` sizes are in vh, for a TV on the wall.
 */

export const WALL_BADGE =
    'px-2 py-0.5 text-sm font-semibold wall:px-[0.9vh] wall:py-[0.3vh] wall:text-[1.7vh]';

export function WallDelta({ metric }: { metric: Metric }) {
    const delta = metricDelta(metric);

    if (!delta || delta.direction === 'flat') {
        return (
            <span className="shrink-0 text-sm whitespace-nowrap text-ink-muted wall:text-[1.6vh]">
                no change
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-sm font-semibold whitespace-nowrap wall:px-[0.9vh] wall:py-[0.3vh] wall:text-[1.7vh]',
                delta.tone === 'good' && 'bg-good-soft text-good',
                delta.tone === 'bad' && 'bg-bad-soft text-bad',
                delta.tone === 'neutral' && 'bg-line text-ink-secondary',
            )}
        >
            {delta.direction === 'up' ? '▲' : '▼'} {delta.label}
        </span>
    );
}

export function BranchPill({
    status,
    branch,
    compact = false,
}: {
    status: DefaultBranchStatus;
    branch: string | null;
    compact?: boolean;
}) {
    const name = branch ?? 'main';

    if (status.state === 'unknown') {
        return (
            <span className="shrink-0 text-sm text-ink-muted wall:text-[1.8vh]">
                No CI on {name}
            </span>
        );
    }

    const failing = status.state === 'failing';
    const [firstWorkflow, ...otherWorkflows] = status.failingWorkflows;
    const workflows =
        otherWorkflows.length > 0
            ? `${firstWorkflow} +${otherWorkflows.length} more`
            : firstWorkflow;

    return (
        <div
            title={failing ? status.failingWorkflows.join('\n') : undefined}
            className={cn(
                'flex max-w-full min-w-0 items-center gap-2 font-semibold wall:gap-[1vh]',
                compact
                    ? 'shrink-0 rounded-full px-3 py-1 text-sm wall:px-[1.4vh] wall:py-[0.6vh] wall:text-[1.8vh]'
                    : 'rounded-2xl px-4 py-2 text-base wall:shrink-0 wall:rounded-full wall:px-[2.2vh] wall:py-[1.2vh] wall:text-[2.6vh]',
                failing ? 'bg-bad text-white' : 'bg-good-soft text-good',
            )}
        >
            <span
                className={cn(
                    'shrink-0 rounded-full',
                    compact
                        ? 'size-2 wall:size-[1.2vh]'
                        : 'size-2.5 wall:size-[1.8vh]',
                    failing ? 'animate-pulse bg-white' : 'bg-good',
                )}
            />
            <span className="flex min-w-0 items-baseline">
                <span className="whitespace-nowrap">
                    {failing ? `${name} is red` : `${name} is green`}
                </span>
                {failing && !compact && status.brokenSince && (
                    <span className="truncate font-normal opacity-90">
                        &nbsp;· for{' '}
                        {timeAgo(status.brokenSince).replace(' ago', '')}
                        {workflows && ` · ${workflows}`}
                    </span>
                )}
            </span>
        </div>
    );
}

export function WallStageBar({ stages }: { stages: CycleStage[] }) {
    const total = stages.reduce((sum, stage) => sum + (stage.seconds ?? 0), 0);

    return (
        <div className="flex h-full flex-col justify-center">
            <div className="flex h-8 w-full gap-0.5 overflow-hidden rounded-lg bg-line wall:h-[4vh] wall:gap-[0.3vh] wall:rounded-[0.8vh]">
                {total > 0 &&
                    stages.map((stage) =>
                        stage.seconds ? (
                            <div
                                key={stage.stage}
                                className="h-full min-w-1 wall:min-w-[0.6vh]"
                                style={{
                                    width: `${(stage.seconds / total) * 100}%`,
                                    background: STAGE_COLORS[stage.stage],
                                }}
                            />
                        ) : null,
                    )}
            </div>
            <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5 wall:mt-[2.5vh] wall:gap-[1.5vh]">
                {stages.map((stage) => (
                    <div key={stage.stage} className="min-w-0">
                        <div className="flex items-center gap-1.5 text-sm whitespace-nowrap text-ink-secondary wall:gap-[0.7vh] wall:text-[1.8vh]">
                            <span
                                className="size-2.5 shrink-0 rounded-sm wall:size-[1.2vh]"
                                style={{
                                    background: STAGE_COLORS[stage.stage],
                                }}
                            />
                            <span className="truncate">{stage.label}</span>
                        </div>
                        <div className="text-2xl font-semibold tracking-tight whitespace-nowrap wall:text-[3.4vh]">
                            {formatDurationCompact(stage.seconds)}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function WaitingList({
    pullRequests,
}: {
    pullRequests: OpenPullRequest[];
}) {
    if (pullRequests.length === 0) {
        return (
            <div className="flex h-full items-center justify-center py-4 text-base text-good wall:py-0 wall:text-[2.4vh]">
                Nothing waiting for review
            </div>
        );
    }

    return (
        <ul className="flex h-full flex-col justify-start gap-4 overflow-hidden wall:gap-[1.4vh]">
            {pullRequests.map((pr) => (
                <li
                    key={pr.number}
                    className="flex items-center gap-3 wall:gap-[1.2vh]"
                >
                    <Avatar
                        src={pr.authorAvatarUrl}
                        login={pr.author}
                        size={32}
                        className="size-8 shrink-0 wall:size-[3.4vh]"
                    />
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-base font-medium wall:text-[2vh]">
                            {pr.title}
                        </div>
                        <div className="truncate text-sm text-ink-muted wall:text-[1.5vh]">
                            #{pr.number} · {pr.author}
                            {pr.status === 'waiting_on_reviewer' &&
                                ` · re-review, round ${pr.rounds + 1}`}
                        </div>
                    </div>
                    <span
                        className={cn(
                            'shrink-0 text-lg font-semibold tabular-nums wall:text-[2.2vh]',
                            pr.isStale && 'text-warn',
                        )}
                    >
                        {formatDurationCompact(pr.waitingSeconds)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * The wall's bottom bar: a progress segment per slide, with the time of day.
 */
export function WallFooter({
    slides,
    index,
    progress,
    paused,
    now,
    onSelect,
    warning,
    labelled = false,
}: {
    slides: { key: string | number; label: string }[];
    index: number;
    progress: number;
    paused: boolean;
    now: number;
    onSelect: (position: number) => void;
    warning?: string | null;
    /** Name each slide under its segment. */
    labelled?: boolean;
}) {
    return (
        <footer className="sticky bottom-0 -mx-4 flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-t border-line bg-page px-4 py-2 text-sm text-ink-muted wall:static wall:mx-0 wall:flex-nowrap wall:gap-[2vh] wall:px-0 wall:py-[1.6vh] wall:text-[1.8vh]">
            <span className="flex items-center gap-2 font-semibold text-ink wall:gap-[0.8vh]">
                <LogoMark className="size-5 wall:size-[2.4vh]" />
                Houston
            </span>
            <div className="order-last flex w-full items-start gap-1.5 wall:order-none wall:w-auto wall:flex-1 wall:gap-[0.8vh]">
                {slides.map((slide, position) => (
                    <button
                        key={slide.key}
                        type="button"
                        onClick={() => onSelect(position)}
                        aria-label={`Show ${slide.label}`}
                        aria-current={position === index}
                        className="group min-w-0 flex-1 cursor-pointer py-3 wall:py-[0.8vh]"
                    >
                        <span className="relative block h-1.5 overflow-hidden rounded-full bg-line group-hover:bg-ink-muted/40 wall:h-[0.6vh]">
                            <span
                                className="absolute inset-y-0 left-0 rounded-full bg-ink-secondary"
                                style={{
                                    width:
                                        position < index
                                            ? '100%'
                                            : position === index
                                              ? `${progress * 100}%`
                                              : '0%',
                                }}
                            />
                        </span>
                        {labelled && (
                            <span
                                className={cn(
                                    'mt-1.5 hidden truncate text-left text-xs sm:block wall:mt-[0.8vh] wall:text-[1.5vh]',
                                    position === index
                                        ? 'text-ink'
                                        : 'text-ink-muted',
                                )}
                            >
                                {slide.label}
                            </span>
                        )}
                    </button>
                ))}
            </div>
            {paused && <span className="text-warn">Paused</span>}
            {warning && <span className="text-warn">{warning}</span>}
            <span className="tabular-nums">
                {new Date(now).toLocaleTimeString('en', {
                    hour: '2-digit',
                    minute: '2-digit',
                })}
            </span>
        </footer>
    );
}
