import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    ALL_TIME,
    historyLabel,
    lookbackReaching,
    repositoriesShortOf,
    samePeriod,
} from '@/lib/lookback';
import { periodDates, periodLabel } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { lookback as updateLookback } from '@/routes/repositories';
import type { RepositoryInfo } from '@/types';

type Pending = {
    period: PeriodFilters;
    apply: () => void;
    shortOf: RepositoryInfo[];
    lookbackDays: number;
};

/**
 * Guards picking a period that starts before the synced history of the repositories on screen. Such
 * a period asks first whether to sync more history: confirming extends the lookback of each
 * repository that falls short, which queues a sync, and then shows the period. Backing out keeps
 * the period that was showing, rather than a report whose older days are quietly missing.
 */
export function useLookbackConfirmation(
    repositories: RepositoryInfo[],
    current: PeriodFilters,
): {
    choosePeriod: (period: PeriodFilters, apply: () => void) => void;
    dialog: ReactNode;
} {
    const { lookbackOptions, demo } = usePage().props;
    const [pending, setPending] = useState<Pending | null>(null);
    const [extending, setExtending] = useState(false);

    const choosePeriod = (period: PeriodFilters, apply: () => void) => {
        // The demo never syncs, so its periods show what there is.
        const shortOf =
            demo || samePeriod(period, current)
                ? []
                : repositoriesShortOf(period, repositories);

        if (shortOf.length === 0) {
            apply();

            return;
        }

        setPending({
            period,
            apply,
            shortOf,
            lookbackDays: lookbackReaching(
                periodDates(period).from,
                lookbackOptions,
            ),
        });
    };

    const extend = (queue: RepositoryInfo[], done: Pending) => {
        const [next, ...rest] = queue;

        if (!next) {
            setExtending(false);
            setPending(null);
            done.apply();

            return;
        }

        router.put(
            updateLookback(next.id).url,
            { lookback_days: done.lookbackDays },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => extend(rest, done),
                onError: () => setExtending(false),
            },
        );
    };

    return {
        choosePeriod,
        dialog: (
            <ConfirmDialog
                open={pending !== null}
                onClose={() => setPending(null)}
                title="Sync more history?"
                confirmLabel={pending ? confirmLabel(pending) : 'Sync'}
                processingLabel="Starting sync…"
                cancelLabel="Keep current period"
                // Extending several repositories takes a request each, so this runs them and closes itself.
                processing={extending}
                onConfirm={() => {
                    if (pending) {
                        setExtending(true);
                        extend(pending.shortOf, pending);
                    }
                }}
            >
                {pending && <Question pending={pending} />}
            </ConfirmDialog>
        ),
    };
}

function plural(count: number, noun: string): string {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function moreDays(pending: Pending): number | null {
    const [repository] = pending.shortOf;

    return pending.shortOf.length === 1 && pending.lookbackDays !== ALL_TIME
        ? pending.lookbackDays - repository.lookback.days
        : null;
}

function confirmLabel(pending: Pending): string {
    const days = moreDays(pending);

    if (days !== null) {
        return `Sync ${plural(days, 'more day')}`;
    }

    return pending.lookbackDays === ALL_TIME
        ? 'Sync the whole history'
        : `Sync ${historyLabel(pending.lookbackDays)}`;
}

function Question({ pending }: { pending: Pending }) {
    const { shortOf, lookbackDays } = pending;
    const days = moreDays(pending);
    const range = periodLabel(pending.period);

    return (
        <div className="flex flex-col gap-2 text-sm text-ink-secondary">
            {shortOf.length === 1 ? (
                <p>
                    This range ({range}) starts before the{' '}
                    {plural(shortOf[0].lookback.days, 'day')} Houston has synced
                    for{' '}
                    <span className="font-medium text-ink">
                        {shortOf[0].fullName}
                    </span>
                    .{' '}
                    {days !== null
                        ? `Sync ${plural(days, 'more day')}?`
                        : 'Sync its whole history?'}
                </p>
            ) : (
                <>
                    <p>
                        This range ({range}) starts before the history Houston
                        has synced for {shortOf.length} repositories. Sync{' '}
                        {historyLabel(lookbackDays)} for them?
                    </p>
                    <ul className="flex flex-col gap-0.5 text-xs">
                        {shortOf.map((repository) => (
                            <li key={repository.id}>
                                <span className="font-medium text-ink">
                                    {repository.fullName}
                                </span>{' '}
                                · {plural(repository.lookback.days, 'day')}{' '}
                                synced
                            </li>
                        ))}
                    </ul>
                </>
            )}
            <p className="text-xs text-ink-muted">
                The older history comes from GitHub in the background and can
                take a while; the report fills in as it arrives.
            </p>
        </div>
    );
}
