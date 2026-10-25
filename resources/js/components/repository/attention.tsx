import { Avatar } from '@/components/ui/avatar';
import { Panel } from '@/components/ui/panel';
import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate, formatDuration } from '@/lib/format';
import type { BotBacklog, Remediation } from '@/types';

export function Remediations({
    remediations,
    showRepository = false,
}: {
    remediations: Remediation[];
    /** Name the repository of each pull request, for reports that span several. */
    showRepository?: boolean;
}) {
    const timeZone = useTimeZone();
    return (
        <Panel
            title="Reverts & hotfixes"
            description="Merged fixes for changes that went wrong"
            bodyClassName="px-0 pb-2 pt-3"
        >
            {remediations.length === 0 ? (
                <p className="px-5 py-6 text-center text-sm text-good">
                    No reverts or hotfixes this period.
                </p>
            ) : (
                <ul className="divide-y divide-line">
                    {remediations.map((pr) => (
                        <li key={pr.url}>
                            <a
                                href={pr.url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-2.5 px-5 py-2.5 hover:bg-line/30"
                            >
                                <Avatar
                                    src={pr.authorAvatarUrl}
                                    login={pr.author}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {pr.title}
                                    </span>
                                    <span className="block text-[11px] text-ink-muted">
                                        {showRepository && pr.repository}#
                                        {pr.number} · merged{' '}
                                        {formatDate(
                                            pr.mergedAt,
                                            false,
                                            timeZone,
                                        )}
                                    </span>
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export function BotPullRequests({ backlog }: { backlog: BotBacklog }) {
    return (
        <Panel
            title="Bot & dependency PRs"
            description="Excluded from the metrics above, but they can quietly pile up"
            bodyClassName="px-0 pb-2 pt-3"
        >
            <div className="grid grid-cols-3 gap-3 px-5 pb-3">
                <Figure label="Open" value={String(backlog.open)} />
                <Figure
                    label="Oldest open"
                    value={formatDuration(backlog.oldestSeconds)}
                    warn={(backlog.oldestSeconds ?? 0) > 7 * 86400}
                />
                <Figure
                    label="Merged this period"
                    value={String(backlog.merged)}
                />
            </div>
            {backlog.oldest.length > 0 && (
                <ul className="divide-y divide-line border-t border-line">
                    {backlog.oldest.map((pr) => (
                        <li key={pr.number}>
                            <a
                                href={pr.url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-3 px-5 py-2 text-sm hover:bg-line/30"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {pr.title}
                                </span>
                                <span className="text-xs whitespace-nowrap text-ink-secondary">
                                    {formatDuration(pr.ageSeconds)}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function Figure({
    label,
    value,
    warn = false,
}: {
    label: string;
    value: string;
    warn?: boolean;
}) {
    return (
        <div>
            <div className="text-[11px] text-ink-muted">{label}</div>
            <div
                className={
                    warn
                        ? 'text-lg font-semibold text-warn'
                        : 'text-lg font-semibold'
                }
            >
                {value}
            </div>
        </div>
    );
}
