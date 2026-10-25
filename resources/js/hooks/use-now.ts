import { useEffect, useState } from 'react';

/**
 * The current time, ticking on an interval while enabled, for live countdowns and elapsed timers.
 */
export function useNow(enabled = true, interval = 1000): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const timer = setInterval(() => setNow(Date.now()), interval);

        return () => clearInterval(timer);
    }, [enabled, interval]);

    return now;
}
