import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AlertIcon } from '@/components/icons';
import { buttonClass } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import {
    ALL_TIME,
    historyGap,
    historyLabel,
    lookbackReaching,
} from '@/lib/lookback';
import { periodDays, periodLabel } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { lookback as updateLookback } from '@/routes/repositories';
import type { RepositoryInfo } from '@/types';

/**
 * Warns whenever the report reads days the cached data doesn't reach back to, however the period
 * was picked: a period that starts before it misses data, and one that fits still compares against
 * a previous period that may not. Offers to sync the missing history unless it is already on its way
 * or this is the demo, which never syncs.
 */
export function HistoryGap({
    repository,
    period,
}: {
    repository: RepositoryInfo;
    period: PeriodFilters;
}) {
    const { lookbackOptions, demo } = usePage().props;
    const [extending, setExtending] = useState(false);
    const gap = historyGap(period, repository.lookback);

    if (!gap) {
        return null;
    }

    const day = (date: string) => formatDate(`${date}T00:00:00`, true);
    const lookbackDays = lookbackReaching(gap.needed, lookbackOptions);
    // The lookback already reaches back far enough when only its backfill is still to come.
    const fetching = repository.lookback.from <= gap.needed;
    const moreDays =
        lookbackDays === ALL_TIME
            ? null
            : lookbackDays - repository.lookback.days;

    return (
        <div className="mt-6 flex flex-col gap-3 rounded-lg border border-warn/30 bg-warn-soft px-4 py-3 text-sm text-ink-secondary sm:flex-row sm:items-center">
            <AlertIcon
                width={16}
                height={16}
                className="hidden shrink-0 text-warn sm:block"
            />
            <p className="min-w-0 flex-1">
                {gap.reaches === 'period' ? (
                    <>
                        The data synced for this repository starts{' '}
                        <span className="font-medium text-ink">
                            {day(gap.coveredFrom)}
                        </span>
                        , so the report on {periodLabel(period)} misses the days
                        from {day(gap.needed)}.
                    </>
                ) : (
                    <>
                        Changes compare against the previous{' '}
                        {periodDays(period)} days, from {day(gap.needed)}, but
                        the data synced for this repository starts{' '}
                        <span className="font-medium text-ink">
                            {day(gap.coveredFrom)}
                        </span>
                        , so they compare against partial data.
                    </>
                )}{' '}
                {fetching &&
                    'The older history is still syncing; the report fills in as it arrives.'}
            </p>
            {!fetching && !demo && (
                <button
                    type="button"
                    disabled={extending}
                    className={buttonClass({ variant: 'secondary' })}
                    onClick={() => {
                        setExtending(true);
                        router.put(
                            updateLookback(repository.id).url,
                            { lookback_days: lookbackDays },
                            {
                                preserveScroll: true,
                                preserveState: true,
                                onFinish: () => setExtending(false),
                            },
                        );
                    }}
                >
                    {extending
                        ? 'Starting sync…'
                        : moreDays !== null && moreDays > 0
                          ? `Sync ${moreDays} more days`
                          : `Sync ${historyLabel(lookbackDays)}`}
                </button>
            )}
        </div>
    );
}
