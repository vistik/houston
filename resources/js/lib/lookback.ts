import { isCustomPeriod, periodDates, previousPeriod } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import type { RepositoryLookback } from '@/types';

/** Stored as the lookback to cache the whole history of a repository. */
export const ALL_TIME = 0;

export function lookbackLabel(days: number): string {
    if (days === ALL_TIME) {
        return 'All time';
    }

    if (days % 365 === 0) {
        return days === 365 ? '1 year' : `${days / 365} years`;
    }

    return `${days} days`;
}

export function historyLabel(days: number): string {
    return days === ALL_TIME
        ? 'the whole history'
        : `${lookbackLabel(days)} of history`;
}

/**
 * Whether two periods cover the same days, ignoring the rolling length a custom or all-time period carries along.
 */
export function samePeriod(a: PeriodFilters, b: PeriodFilters): boolean {
    if (a.all || b.all) {
        return a.all === b.all;
    }

    if (isCustomPeriod(a) || isCustomPeriod(b)) {
        return a.from === b.from && a.to === b.to;
    }

    return a.days === b.days;
}

/**
 * The repositories whose synced history starts after the period does. All time only reaches as far
 * back as the history does, and a repository synced for all time has nothing older to fetch.
 */
export function repositoriesShortOf<
    Repository extends { lookback: RepositoryLookback },
>(period: PeriodFilters, repositories: Repository[]): Repository[] {
    if (period.all) {
        return [];
    }

    const { from } = periodDates(period);

    return repositories.filter(
        (repository) =>
            !repository.lookback.allTime && from < repository.lookback.from,
    );
}

/**
 * Days a report reads that the cached data doesn't reach back to: `period` when the period itself
 * starts before it, `previous` when only the equally long period the changes compare against does.
 * `needed` is the earliest day the report reads.
 */
export type HistoryGap = {
    reaches: 'period' | 'previous';
    needed: string;
    coveredFrom: string;
};

/**
 * Where the report on a period runs past the cached data of a repository, or null when it doesn't.
 * All time only reaches as far back as the history does, and nothing predates a repository synced
 * for all time, so its gap closes once its backfill reaches back to when it was created.
 */
export function historyGap(
    period: PeriodFilters,
    lookback: RepositoryLookback,
): HistoryGap | null {
    const { coveredFrom } = lookback;

    if (period.all || coveredFrom === null) {
        return null;
    }

    const earliest = (day: string) =>
        lookback.allTime && day < lookback.from ? lookback.from : day;
    const start = earliest(periodDates(period).from);

    if (start < coveredFrom) {
        return { reaches: 'period', needed: start, coveredFrom };
    }

    const previous = earliest(previousPeriod(period).from);

    return previous < coveredFrom
        ? { reaches: 'previous', needed: previous, coveredFrom }
        : null;
}

/**
 * The shortest offered lookback that reaches back to the date, or the whole history when none does.
 */
export function lookbackReaching(
    from: string,
    options: number[],
    today: Date = new Date(),
): number {
    const start = new Date(`${from}T00:00:00`);
    const midnight = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate(),
    );
    const needed = Math.round(
        (midnight.getTime() - start.getTime()) / 86_400_000,
    );

    return (
        options
            .filter((days) => days !== ALL_TIME && days >= needed)
            .sort((a, b) => a - b)[0] ?? ALL_TIME
    );
}
