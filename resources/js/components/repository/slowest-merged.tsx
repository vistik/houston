import { Avatar } from '@/components/ui/avatar';
import { Panel } from '@/components/ui/panel';
import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate, formatDuration } from '@/lib/format';
import type { MergedPullRequest } from '@/types';

/**
 * The pull requests merged in the period that took longest to land.
 */
export function SlowestMerged({
    pullRequests,
    showRepository = false,
}: {
    pullRequests: MergedPullRequest[];
    /** Name the repository of each pull request, for reports that span several. */
    showRepository?: boolean;
}) {
    const timeZone = useTimeZone();
    if (pullRequests.length === 0) {
        return (
            <Panel>
                <p className="py-4 text-center text-sm text-ink-muted">
                    Nothing merged in this period.
                </p>
            </Panel>
        );
    }

    return (
        <Panel bodyClassName="p-0">
            <ul className="divide-y divide-line">
                {pullRequests.map((pr) => (
                    <li key={pr.url}>
                        <a
                            href={pr.url}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-3 px-5 py-3 hover:bg-line/30"
                        >
                            <Avatar
                                src={pr.authorAvatarUrl}
                                login={pr.author}
                                size={24}
                            />
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">
                                    {pr.title}
                                </div>
                                <div className="text-[11px] text-ink-muted">
                                    {showRepository && pr.repository}#
                                    {pr.number} by {pr.author ?? 'ghost'} ·
                                    merged{' '}
                                    {formatDate(pr.mergedAt, false, timeZone)} ·{' '}
                                    {pr.sizeLabel} ({pr.size} lines)
                                </div>
                            </div>
                            <div className="hidden text-right sm:block">
                                <div className="text-[11px] text-ink-muted">
                                    Pickup
                                </div>
                                <div className="tabular text-sm">
                                    {formatDuration(pr.pickupTime)}
                                </div>
                            </div>
                            <div className="w-20 text-right">
                                <div className="text-[11px] text-ink-muted">
                                    To merge
                                </div>
                                <div className="tabular text-sm font-semibold">
                                    {formatDuration(pr.cycleTime)}
                                </div>
                            </div>
                        </a>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}
