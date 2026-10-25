import { useState } from 'react';
import { Tooltip } from '@/components/ui/hover-card';
import { formatDuration } from '@/lib/format';
import type { CycleStage } from '@/types';

export const STAGE_COLORS: Record<CycleStage['stage'], string> = {
    coding: 'var(--series-1)',
    pickup: 'var(--series-2)',
    author: 'var(--series-5)',
    review: 'var(--series-3)',
    merge: 'var(--series-4)',
};

const STAGE_HINTS: Record<CycleStage['stage'], string> = {
    coding: 'First commit → ready for review',
    pickup: 'Waiting for a first response',
    author: 'Waiting on the author to respond',
    review: 'Waiting on reviewers again',
    merge: 'Approved → merged',
};

/**
 * Horizontal stacked bar showing where the average merged pull request spends its time.
 */
export function StageBar({ stages }: { stages: CycleStage[] }) {
    const [hovered, setHovered] = useState<CycleStage['stage'] | null>(null);
    const total = stages.reduce((sum, stage) => sum + (stage.seconds ?? 0), 0);
    const share = (seconds: number | null) =>
        total > 0 && seconds ? Math.round((seconds / total) * 100) : 0;

    return (
        <div>
            <div className="flex h-9 w-full gap-0.5 overflow-hidden rounded-md bg-line/60">
                {total > 0 &&
                    stages.map((stage) =>
                        stage.seconds ? (
                            <Tooltip
                                key={stage.stage}
                                content={
                                    <>
                                        <div className="flex justify-between gap-4 font-medium">
                                            {stage.label}
                                            <span className="tabular">
                                                {formatDuration(stage.seconds)}{' '}
                                                <span className="font-normal text-ink-muted">
                                                    {share(stage.seconds)}%
                                                </span>
                                            </span>
                                        </div>
                                        <div className="text-ink-muted">
                                            {STAGE_HINTS[stage.stage]}
                                        </div>
                                    </>
                                }
                                className="h-full min-w-1 transition-opacity first:rounded-l-md last:rounded-r-md"
                                style={{
                                    width: `${(stage.seconds / total) * 100}%`,
                                    background: STAGE_COLORS[stage.stage],
                                    opacity:
                                        hovered && hovered !== stage.stage
                                            ? 0.35
                                            : 1,
                                }}
                            >
                                <div
                                    className="size-full"
                                    onMouseEnter={() => setHovered(stage.stage)}
                                    onMouseLeave={() => setHovered(null)}
                                />
                            </Tooltip>
                        ) : null,
                    )}
            </div>

            <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-5">
                {stages.map((stage) => (
                    <div
                        key={stage.stage}
                        className="transition-opacity"
                        style={{
                            opacity:
                                hovered && hovered !== stage.stage ? 0.45 : 1,
                        }}
                        onMouseEnter={() => setHovered(stage.stage)}
                        onMouseLeave={() => setHovered(null)}
                    >
                        <div className="flex items-center gap-1.5 text-xs text-ink-secondary">
                            <span
                                className="size-2 rounded-sm"
                                style={{
                                    background: STAGE_COLORS[stage.stage],
                                }}
                            />
                            {stage.label}
                        </div>
                        <div className="mt-0.5 flex items-baseline gap-1.5">
                            <span className="text-lg font-semibold tracking-tight">
                                {formatDuration(stage.seconds)}
                            </span>
                            {total > 0 && (
                                <span className="text-xs text-ink-muted">
                                    {share(stage.seconds)}%
                                </span>
                            )}
                        </div>
                        <div className="text-[11px] text-ink-muted">
                            {STAGE_HINTS[stage.stage]}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
