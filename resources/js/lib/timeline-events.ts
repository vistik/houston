import { createContext, use } from 'react';
import type { FocusedTimelineEvent, TimelineEvent } from '@/types';

const DAY = 86_400_000;

/**
 * The events to mark on the page's timelines, how long each charted bucket is, and where opening an
 * event goes. `focused` is the event the report compares before and after, whose charts split at it,
 * and `clearHref` the report on its usual period. Null when the page marks no events.
 */
export const TimelineEvents = createContext<{
    events: TimelineEvent[];
    focused: FocusedTimelineEvent | null;
    clearHref: string;
    bucketDays: number;
    href: (event: TimelineEvent) => string;
} | null>(null);

/**
 * What a report's changes compare with: the time before the event it focuses on, or the previous period.
 */
export function useComparedWith(): string {
    return use(TimelineEvents)?.focused
        ? 'the time before'
        : 'the previous period';
}

/**
 * Where each event falls on a chart whose buckets start on `dates`, in buckets from the start of the
 * first one: 2.5 is halfway through the third bucket. Events before the first bucket or after the
 * last are left out.
 */
export function eventPositions(
    events: TimelineEvent[],
    dates: string[],
    bucketDays: number,
): { event: TimelineEvent; at: number }[] {
    const starts = dates.map(utcDay);

    return events.flatMap((event) => {
        const day = utcDay(event.date);
        const index = starts.findLastIndex((start) => start <= day);

        if (index === -1) {
            return [];
        }

        const offset = (day - starts[index]) / (bucketDays * DAY);

        return offset < 1 ? [{ event, at: index + offset }] : [];
    });
}

function utcDay(date: string): number {
    return Date.parse(`${date}T00:00:00Z`);
}
