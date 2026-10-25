import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    ALL_TIME,
    historyGap,
    lookbackReaching,
    repositoriesShortOf,
    samePeriod,
} from './lookback';
import type { PeriodFilters } from './periods';

const OPTIONS = [30, 90, 180, 365, 730, ALL_TIME];

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

const repository = (from: string, allTime = false) => ({
    lookback: { days: 180, from, allTime, coveredFrom: from },
});

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 9, 23, 15, 30));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('repositoriesShortOf', () => {
    const synced = repository('2026-04-26');

    it('picks repositories whose synced history starts after the period', () => {
        expect(repositoriesShortOf(rolling(365), [synced])).toEqual([synced]);
        expect(
            repositoriesShortOf(custom('2026-01-01', '2026-02-01'), [synced]),
        ).toEqual([synced]);
    });

    it('leaves out repositories whose synced history covers the period', () => {
        expect(repositoriesShortOf(rolling(180), [synced])).toEqual([]);
        expect(
            repositoriesShortOf(custom('2026-04-26', '2026-05-01'), [synced]),
        ).toEqual([]);
    });

    it('never asks for all time or for repositories synced for all time', () => {
        expect(repositoriesShortOf(rolling(365, true), [synced])).toEqual([]);
        expect(
            repositoriesShortOf(rolling(365), [repository('2026-04-26', true)]),
        ).toEqual([]);
    });
});

describe('historyGap', () => {
    // A rolling period is today and the days before it, so "last 365 days" on 2026-10-23 starts on
    // 2025-10-24.
    const synced = repository('2026-04-26').lookback;

    it('flags a period that starts before the synced data', () => {
        expect(historyGap(rolling(365), synced)).toEqual({
            reaches: 'period',
            needed: '2025-10-24',
            coveredFrom: '2026-04-26',
        });
    });

    it('flags a period that fits when the period it compares against does not', () => {
        expect(historyGap(rolling(180), synced)).toEqual({
            reaches: 'previous',
            needed: '2025-10-29',
            coveredFrom: '2026-04-26',
        });
        expect(historyGap(rolling(90), synced)).toBeNull();
    });

    it('flags history a backfill has yet to fetch', () => {
        expect(
            historyGap(rolling(30), { ...synced, coveredFrom: '2026-10-01' }),
        ).toEqual({
            reaches: 'period',
            needed: '2026-09-24',
            coveredFrom: '2026-10-01',
        });
    });

    it('stops at the creation of a repository synced for all time', () => {
        const allTime = { ...synced, allTime: true, from: '2026-05-01' };

        expect(
            historyGap(rolling(365), { ...allTime, coveredFrom: '2026-05-01' }),
        ).toBeNull();
        expect(
            historyGap(rolling(365), { ...allTime, coveredFrom: '2026-09-01' }),
        ).toEqual({
            reaches: 'period',
            needed: '2026-05-01',
            coveredFrom: '2026-09-01',
        });
    });

    it('never flags all time or a repository yet to sync', () => {
        expect(historyGap(rolling(180, true), synced)).toBeNull();
        expect(
            historyGap(rolling(365), { ...synced, coveredFrom: null }),
        ).toBeNull();
    });
});

describe('lookbackReaching', () => {
    it('picks the shortest offered lookback reaching back to the date', () => {
        expect(lookbackReaching('2026-04-26', OPTIONS)).toBe(180);
        expect(lookbackReaching('2026-04-25', OPTIONS)).toBe(365);
        expect(lookbackReaching('2025-01-01', OPTIONS)).toBe(730);
    });

    it('falls back to the whole history when no lookback reaches back far enough', () => {
        expect(lookbackReaching('2020-01-01', OPTIONS)).toBe(ALL_TIME);
    });
});

describe('samePeriod', () => {
    it('compares the days periods cover', () => {
        expect(samePeriod(rolling(30), rolling(30))).toBe(true);
        expect(samePeriod(rolling(30), rolling(90))).toBe(false);
        expect(samePeriod(rolling(30, true), rolling(90, true))).toBe(true);
        expect(
            samePeriod(custom('2026-01-01', '2026-02-01'), {
                ...custom('2026-01-01', '2026-02-01'),
                days: 90,
            }),
        ).toBe(true);
        expect(
            samePeriod(custom('2026-01-01', '2026-02-01'), rolling(30)),
        ).toBe(false);
    });
});
