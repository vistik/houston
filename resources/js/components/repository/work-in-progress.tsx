import { useState } from 'react';
import { CheckIcon, DotIcon, XIcon } from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import type { BadgeTone } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';
import { Tooltip } from '@/components/ui/hover-card';
import { Panel } from '@/components/ui/panel';
import { formatDuration } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { OpenPullRequest, ReviewStatus } from '@/types';

const STATUS: Record<ReviewStatus, { label: string; tone: BadgeTone }> = {
    awaiting_review: { label: 'Awaiting review', tone: 'warn' },
    waiting_on_reviewer: { label: 'Waiting on reviewer', tone: 'accent' },
    waiting_on_author: { label: 'Waiting on author', tone: 'bad' },
    approved: { label: 'Approved', tone: 'good' },
    draft: { label: 'Draft', tone: 'neutral' },
};

const ORDER: ReviewStatus[] = [
    'awaiting_review',
    'waiting_on_reviewer',
    'waiting_on_author',
    'approved',
    'draft',
];

export function WorkInProgress({
    pullRequests,
    showRepository = false,
}: {
    pullRequests: OpenPullRequest[];
    /** Name the repository of each pull request, for reports that span several. */
    showRepository?: boolean;
}) {
    const [filter, setFilter] = useState<ReviewStatus | 'stale' | null>(null);

    const counts = ORDER.map((status) => ({
        status,
        count: pullRequests.filter((pr) => pr.status === status).length,
    }));
    const staleCount = pullRequests.filter((pr) => pr.isStale).length;

    const visible = pullRequests.filter((pr) =>
        filter === null
            ? true
            : filter === 'stale'
              ? pr.isStale
              : pr.status === filter,
    );

    return (
        <Panel
            title="Open pull requests"
            description="Everything in flight right now and what it is waiting on"
            bodyClassName="p-0"
        >
            <div className="flex flex-wrap gap-1.5 px-5 pt-4 pb-3">
                <FilterChip
                    active={filter === null}
                    onClick={() => setFilter(null)}
                >
                    All{' '}
                    <span className="text-ink-muted">
                        {pullRequests.length}
                    </span>
                </FilterChip>
                {counts.map(({ status, count }) =>
                    count > 0 ? (
                        <FilterChip
                            key={status}
                            active={filter === status}
                            onClick={() => setFilter(status)}
                        >
                            {STATUS[status].label}{' '}
                            <span className="text-ink-muted">{count}</span>
                        </FilterChip>
                    ) : null,
                )}
                {staleCount > 0 && (
                    <FilterChip
                        active={filter === 'stale'}
                        onClick={() => setFilter('stale')}
                    >
                        Stale{' '}
                        <span className="text-ink-muted">{staleCount}</span>
                    </FilterChip>
                )}
            </div>

            {visible.length === 0 ? (
                <p className="border-t border-line px-5 py-10 text-center text-sm text-ink-muted">
                    No open pull requests.
                </p>
            ) : (
                <div className="max-h-[480px] overflow-auto border-t border-line">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-surface text-left text-[11px] font-medium text-ink-muted">
                            <tr className="border-b border-line">
                                <th className="px-5 py-2 font-medium">
                                    Pull request
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="hidden px-3 py-2 font-medium md:table-cell">
                                    Checks
                                </th>
                                <th className="hidden px-3 py-2 font-medium sm:table-cell">
                                    Size
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    <Tooltip
                                        content="Working time in the current status"
                                        className="cursor-help underline decoration-line-strong decoration-dotted underline-offset-2"
                                    >
                                        Waiting
                                    </Tooltip>
                                </th>
                                <th className="hidden px-5 py-2 text-right font-medium sm:table-cell">
                                    Age
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((pr) => (
                                <tr
                                    key={pr.url}
                                    className="border-b border-line last:border-0 hover:bg-line/30"
                                >
                                    <td className="max-w-0 px-5 py-2.5">
                                        <a
                                            href={pr.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex min-w-0 items-center gap-2.5"
                                        >
                                            <Avatar
                                                src={pr.authorAvatarUrl}
                                                login={pr.author}
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium hover:text-accent">
                                                    {pr.title}
                                                </span>
                                                <span className="block truncate text-[11px] text-ink-muted">
                                                    {showRepository &&
                                                        pr.repository}
                                                    #{pr.number} by{' '}
                                                    {pr.author ?? 'ghost'}
                                                    {pr.reviewers.length > 0 &&
                                                        ` · reviewed by ${pr.reviewers.join(', ')}`}
                                                </span>
                                            </span>
                                        </a>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-1 whitespace-nowrap">
                                            <Badge
                                                tone={STATUS[pr.status].tone}
                                            >
                                                {STATUS[pr.status].label}
                                            </Badge>
                                            {pr.isStale && (
                                                <Badge tone="warn">Stale</Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="hidden px-3 py-2.5 whitespace-nowrap md:table-cell">
                                        <ChecksState state={pr.checks} />
                                    </td>
                                    <td className="tabular hidden px-3 py-2.5 whitespace-nowrap text-ink-secondary sm:table-cell">
                                        <span className="font-medium text-ink">
                                            {pr.sizeLabel}
                                        </span>{' '}
                                        <span className="text-[11px]">
                                            {pr.size} lines
                                        </span>
                                    </td>
                                    <td className="tabular px-3 py-2.5 text-right font-medium whitespace-nowrap">
                                        {formatDuration(pr.waitingSeconds)}
                                        {pr.rounds > 0 && (
                                            <Tooltip
                                                content="Times the PR went back to its author"
                                                className="flex justify-end text-[11px] font-normal text-ink-muted"
                                            >
                                                {pr.rounds} round
                                                {pr.rounds === 1 ? '' : 's'}
                                            </Tooltip>
                                        )}
                                    </td>
                                    <td className="tabular hidden px-5 py-2.5 text-right whitespace-nowrap text-ink-secondary sm:table-cell">
                                        {formatDuration(pr.ageSeconds)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Panel>
    );
}

function FilterChip({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-full border px-2.5 py-1 text-xs font-medium transition',
                active
                    ? 'border-ink bg-ink text-page [&_span]:text-page/70'
                    : 'border-line bg-surface text-ink-secondary hover:border-line-strong hover:text-ink',
            )}
        >
            {children}
        </button>
    );
}

export function ChecksState({ state }: { state: string | null }) {
    if (!state) {
        return <span className="text-xs text-ink-muted">—</span>;
    }

    if (state === 'SUCCESS') {
        return (
            <span className="inline-flex items-center gap-1 text-xs text-good">
                <CheckIcon width={13} height={13} strokeWidth={2.5} /> Passing
            </span>
        );
    }

    if (state === 'FAILURE' || state === 'ERROR') {
        return (
            <span className="inline-flex items-center gap-1 text-xs text-bad">
                <XIcon width={13} height={13} strokeWidth={2.5} /> Failing
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1 text-xs text-warn">
            <DotIcon width={13} height={13} /> Pending
        </span>
    );
}
