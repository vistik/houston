import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Must match the sticky header's height in the app layout.
 */
const HEADER_HEIGHT = 56;

/**
 * Page controls that stay in reach under the header while scrolling, and only get a backdrop once they stick.
 */
export function StickyToolbar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [stuck, setStuck] = useState(false);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        // Shrinking the root by one pixel past the header clips the toolbar exactly when it sticks.
        const observer = new IntersectionObserver(
            ([entry]) =>
                setStuck(
                    entry.intersectionRatio < 1 &&
                        entry.boundingClientRect.top <= HEADER_HEIGHT + 1,
                ),
            {
                rootMargin: `-${HEADER_HEIGHT + 1}px 0px 0px 0px`,
                threshold: 1,
            },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={cn(
                'sticky top-14 z-10 -mx-4 flex flex-wrap items-center gap-3 border-b px-4 py-3 transition-colors sm:-mx-6 sm:px-6',
                stuck
                    ? 'border-line bg-page/80 backdrop-blur-md'
                    : 'border-transparent',
                className,
            )}
        >
            {children}
        </div>
    );
}
