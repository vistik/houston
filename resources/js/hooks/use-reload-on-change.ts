import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Reload the given props whenever the version changes, e.g. when a sync brings in new data.
 */
export function useReloadOnChange(version: string, only: string[]) {
    const previous = useRef(version);

    useEffect(() => {
        if (previous.current === version) {
            return;
        }

        previous.current = version;
        router.reload({ only });
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only the version should trigger a reload.
    }, [version]);
}
