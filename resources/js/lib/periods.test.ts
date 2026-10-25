import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { PeriodFilters } from './periods';
import {
    bucketLabel,
    fitsPeriod,
    GRANULARITY_OPTIONS,
    isCustomPeriod,
    periodDates,
    periodDays,
    periodLabel,
    periodQuery,
    previousPeriod,
    toDateInput,
} from './periods';

const rolling = (days: number, all = false): PeriodFilters => ({
    days,
    from: null,
    to: null,
    all,
});

const custom = (from: string, to: string): PeriodFilters => ({
    days: 30,
    from,
    to,
    all: false,
});

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 9, 23, 15, 30));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('fitsPeriod', () => {
    it('needs at least two buckets in the period', () => {
        expect(fitsPeriod(1, 2)).toBe(true);
        expect(fitsPeriod(7, 14)).toBe(true);
        expect(fitsPeriod(7, 13)).toBe(false);
        expect(fitsPeriod(30, 45)).toBe(false);
        expect(fitsPeriod(30, 60)).toBe(true);
    });

    it('matches the granularity options', () => {
        expect(GRANULARITY_OPTIONS.map((option) => option.days)).toEqual([
            1, 7, 30,
        ]);
    });
});

describe('isCustomPeriod', () => {
    it('needs both a start and an end date', () => {
        expect(isCustomPeriod(custom('2026-10-01', '2026-10-10'))).toBe(true);
        expect(isCustomPeriod({ ...rolling(30), from: '2026-10-01' })).toBe(
            false,
        );
        expect(isCustomPeriod(rolling(30))).toBe(false);
    });
});

describe('periodQuery', () => {
    it('sends the dates of a custom range', () => {
        expect(periodQuery(custom('2026-10-01', '2026-10-10'))).toEqual({
            from: '2026-10-01',
            to: '2026-10-10',
        });
    });

    it('prefers a custom range over all time', () => {
        expect(
            periodQuery({ ...custom('2026-10-01', '2026-10-10'), all: true }),
        ).toEqual({ from: '2026-10-01', to: '2026-10-10' });
    });

    it('sends the days of a rolling period', () => {
        expect(periodQuery(rolling(30))).toEqual({ days: 30 });
        expect(periodQuery(rolling(30, true))).toEqual({ days: 'all' });
    });
});

describe('periodLabel', () => {
    it('labels rolling periods', () => {
        expect(periodLabel(rolling(30, true))).toBe('all time');
        expect(periodLabel(rolling(30))).toBe('last 30 days');
        expect(periodLabel(rolling(180))).toBe('last 6 months');
        expect(periodLabel(rolling(21))).toBe('last 21 days');
    });

    it('leaves out the current year', () => {
        expect(periodLabel(custom('2026-10-01', '2026-10-15'))).toBe(
            'Oct 1 – Oct 15',
        );
        expect(periodLabel(custom('2026-10-01', '2026-10-01'))).toBe('Oct 1');
    });

    it('shows the year once for a range within an earlier year', () => {
        expect(periodLabel(custom('2025-03-01', '2025-03-31'))).toBe(
            'Mar 1 – Mar 31, 2025',
        );
        expect(periodLabel(custom('2025-03-01', '2025-03-01'))).toBe(
            'Mar 1, 2025',
        );
    });

    it('shows both years for a range across years', () => {
        expect(periodLabel(custom('2025-12-20', '2026-01-05'))).toBe(
            'Dec 20, 2025 – Jan 5, 2026',
        );
    });
});

describe('periodDates', () => {
    it('keeps custom ranges as they are', () => {
        const period = custom('2026-10-01', '2026-10-10');

        expect(periodDates(period)).toBe(period);
    });

    it('ends rolling periods today, counting today as one of their days', () => {
        expect(periodDates(rolling(7))).toEqual({
            days: 7,
            from: '2026-10-17',
            to: '2026-10-23',
            all: false,
        });
        expect(periodDates(rolling(30, true))).toMatchObject({
            from: '2026-09-24',
            to: '2026-10-23',
            all: false,
        });
    });
});

describe('periodDays', () => {
    it('uses the days of a rolling period', () => {
        expect(periodDays(rolling(45))).toBe(45);
    });

    it('counts both ends of a custom range', () => {
        expect(periodDays(custom('2026-10-01', '2026-10-01'))).toBe(1);
        expect(periodDays(custom('2026-10-01', '2026-10-31'))).toBe(31);
        expect(periodDays(custom('2024-02-01', '2024-03-01'))).toBe(30);
    });
});

describe('previousPeriod', () => {
    it('is the equally long range right before a custom range', () => {
        expect(previousPeriod(custom('2026-10-01', '2026-10-10'))).toEqual({
            days: 10,
            from: '2026-09-21',
            to: '2026-09-30',
            all: false,
        });
    });

    it('ends the day before a rolling period starts', () => {
        expect(previousPeriod(rolling(7))).toEqual({
            days: 7,
            from: '2026-10-10',
            to: '2026-10-16',
            all: false,
        });
    });

    it('crosses month and year boundaries', () => {
        expect(previousPeriod(custom('2026-01-01', '2026-01-31'))).toEqual({
            days: 31,
            from: '2025-12-01',
            to: '2025-12-31',
            all: false,
        });
    });
});

describe('toDateInput', () => {
    it('zero-pads the month and day', () => {
        expect(toDateInput(new Date(2026, 0, 5))).toBe('2026-01-05');
        expect(toDateInput(new Date(2026, 11, 31, 23, 59))).toBe('2026-12-31');
    });
});

describe('bucketLabel', () => {
    it('shows the date with the year for daily buckets', () => {
        expect(
            bucketLabel('2026-10-06', { bucketDays: 1, to: '2026-10-23' }),
        ).toBe('Oct 6, 2026');
    });

    it('shows the range of days a bucket covers', () => {
        expect(
            bucketLabel('2026-10-05', { bucketDays: 7, to: '2026-10-23' }),
        ).toBe('Oct 5 – Oct 11');
        expect(
            bucketLabel('2026-09-01', { bucketDays: 30, to: '2026-12-01' }),
        ).toBe('Sep 1 – Sep 30');
    });

    it('stops the last bucket at the end of the period', () => {
        expect(
            bucketLabel('2026-10-05', { bucketDays: 7, to: '2026-10-08' }),
        ).toBe('Oct 5 – Oct 7');
    });

    it('ends the period at midnight in the timezone it is shown in', () => {
        expect(
            bucketLabel('2026-10-05', {
                bucketDays: 7,
                to: '2026-10-08T00:00:00-04:00',
            }),
        ).toBe('Oct 5 – Oct 7');
        expect(
            bucketLabel('2026-10-05', {
                bucketDays: 7,
                to: '2026-10-07T21:30:00+02:00',
            }),
        ).toBe('Oct 5 – Oct 7');
    });
});
