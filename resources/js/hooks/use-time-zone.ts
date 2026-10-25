import { usePage } from '@inertiajs/react';

/**
 * The timezone reports are shown in, from the report defaults, for showing moments on the same
 * clock the charts use.
 */
export function useTimeZone(): string {
    return usePage().props.timezone;
}
