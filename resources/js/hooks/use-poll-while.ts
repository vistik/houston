import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Reload the given props on an interval for as long as the condition holds.
 */
export function usePollWhile(
    condition: boolean,
    only: string[],
    interval = 3000,
) {
    const { start, stop } = usePoll(interval, { only }, { autoStart: false });

    useEffect(() => {
        if (condition) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [condition, start, stop]);
}
