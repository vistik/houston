import { AlertIcon } from '@/components/icons';
import { Tooltip } from '@/components/ui/hover-card';
import { timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RepositoryInfo } from '@/types';

export function SyncStatus({ repository }: { repository: RepositoryInfo }) {
    const { sync: state } = repository;

    return (
        <div className="flex items-center gap-2">
            <Tooltip content={state.status === 'failed' && state.error}>
                <span
                    className={cn(
                        'flex items-center gap-1.5 text-xs text-ink-muted',
                        state.status === 'failed' && 'text-bad',
                    )}
                >
                    {state.inProgress ? (
                        <>
                            <span className="relative flex size-2">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60" />
                                <span className="relative inline-flex size-2 rounded-full bg-accent" />
                            </span>
                            {state.hasData
                                ? 'Refreshing from GitHub…'
                                : 'Fetching history from GitHub…'}
                        </>
                    ) : state.status === 'failed' ? (
                        <>
                            <AlertIcon width={13} height={13} />
                            Sync failed
                        </>
                    ) : (
                        <>Synced {timeAgo(state.finishedAt)}</>
                    )}
                </span>
            </Tooltip>
        </div>
    );
}
