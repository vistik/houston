import { createContext } from 'react';
import { formatDate } from '@/lib/format';

export const PERIOD_OPTIONS = [
    { value: 7, label: 'Last 7 days' },
    { value: 14, label: 'Last 14 days' },
    { value: 30, label: 'Last 30 days' },
    { value: 45, label: 'Last 45 days' },
    { value: 90, label: 'Last 90 days' },
    { value: 180, label: 'Last 6 months' },
    { value: 365, label: 'Last 12 months' },
];

export type Granularity = 'day' | 'week' | 'month';

/**
 * How charts can group a period. Months are rolling 30-day buckets, like the periods themselves.
 */
export const GRANULARITY_OPTIONS: {
    value: Granularity;
    label: string;
    days: number;
}[] = [
    { value: 'day', label: 'Day', days: 1 },
    { value: 'week', label: 'Week', days: 7 },
    { value: 'month', label: 'Month', days: 30 },
];

/**
 * A granularity needs at least two buckets in the period to show a trend.
 */
export function fitsPeriod(
    granularityDays: number,
    periodDays: number,
): boolean {
    return periodDays >= 2 * granularityDays;
}

/**
 * Either a rolling "last N days" period, all of the cached history when `all` is set, or a custom
 * range of whole days when `from` and `to` are set.
 */
export type PeriodFilters = {
    days: number;
    from: string | null;
    to: string | null;
    all: boolean;
};

export function isCustomPeriod(
    period: PeriodFilters,
): period is PeriodFilters & { from: string; to: string } {
    return period.from !== null && period.to !== null;
}

export function periodQuery(period: PeriodFilters) {
    if (isCustomPeriod(period)) {
        return { from: period.from, to: period.to };
    }

    return { days: period.all ? 'all' : period.days };
}

/** The filters of a repository report, as a repository page and its metric pages take them. */
export type ReportFilters = PeriodFilters & {
    granularity: Granularity | null;
    complete: boolean;
    bots: boolean;
    /** The timeline event the period runs from, through today. */
    event: number | null;
};

export function reportQuery(filters: ReportFilters) {
    return {
        ...periodQuery(filters),
        granularity: filters.granularity ?? undefined,
        // Always explicit, so unticking it overrides a default that leaves the current bucket out.
        complete: filters.complete ? 1 : 0,
        bots: filters.bots ? 1 : undefined,
        event: filters.event ?? undefined,
    };
}

export function periodLabel(period: PeriodFilters): string {
    if (!isCustomPeriod(period)) {
        if (period.all) {
            return 'all time';
        }

        const preset = PERIOD_OPTIONS.find(
            (option) => option.value === period.days,
        );

        return preset ? preset.label.toLowerCase() : `last ${period.days} days`;
    }

    const from = localDate(period.from);
    const to = localDate(period.to);
    const sameYear = from.getFullYear() === to.getFullYear();
    const showYear = !sameYear || to.getFullYear() !== new Date().getFullYear();
    const format = (date: Date, withYear: boolean) =>
        date.toLocaleDateString('en', {
            month: 'short',
            day: 'numeric',
            ...(withYear ? { year: 'numeric' } : {}),
        });

    return period.from === period.to
        ? format(from, showYear)
        : `${format(from, showYear && !sameYear)} – ${format(to, showYear)}`;
}

/**
 * The period the page reports on, for components that explain a metric without being handed it.
 */
export const ReportPeriod = createContext<PeriodFilters | null>(null);

type DatedPeriod = PeriodFilters & { from: string; to: string };

/**
 * The period as concrete dates: custom ranges as they are, rolling periods as today and the days
 * before it.
 */
export function periodDates(period: PeriodFilters): DatedPeriod {
    if (isCustomPeriod(period)) {
        return period;
    }

    const today = new Date();

    return {
        days: period.days,
        from: toDateInput(addDays(today, 1 - period.days)),
        to: toDateInput(today),
        all: false,
    };
}

/**
 * How many days the period covers.
 */
export function periodDays(period: PeriodFilters): number {
    if (!isCustomPeriod(period)) {
        return period.days;
    }

    return (
        Math.round(
            (localDate(period.to).getTime() -
                localDate(period.from).getTime()) /
                86_400_000,
        ) + 1
    );
}

/**
 * The equally long period right before, which "previous" values come from.
 */
export function previousPeriod(period: PeriodFilters): DatedPeriod {
    const { from } = periodDates(period);
    const days = periodDays(period);
    const start = localDate(from);

    return {
        days,
        from: toDateInput(addDays(start, -days)),
        to: toDateInput(addDays(start, -1)),
        all: false,
    };
}

/**
 * Dates as YYYY-MM-DD in the viewer's timezone, as date inputs expect.
 */
export function toDateInput(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function localDate(value: string): Date {
    return new Date(`${value}T00:00:00`);
}

function addDays(date: Date, days: number): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

/**
 * The days a trend bucket covers, e.g. "Oct 6, 2026" or "Oct 6 – Oct 12". The last bucket stops at the end of the period.
 */
export function bucketLabel(
    start: string,
    period: { bucketDays: number; to: string },
): string {
    if (period.bucketDays === 1) {
        return formatDate(start, true);
    }

    const day = 24 * 60 * 60 * 1000;
    const lastDay = Math.min(
        Date.parse(start) + (period.bucketDays - 1) * day,
        wallClock(period.to) - 1,
    );

    return `${formatDate(start)} – ${formatDate(new Date(lastDay).toISOString().slice(0, 10))}`;
}

/**
 * A date or moment's own date and time of day, read as if it were UTC, so a period that ends at
 * midnight in the timezone reports are shown in ends at midnight here too, whatever its offset.
 */
function wallClock(value: string): number {
    return Date.parse(
        `${value.slice(0, 10)}T${value.slice(11, 19) || '00:00:00'}Z`,
    );
}
