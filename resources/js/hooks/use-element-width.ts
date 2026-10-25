import { useEffect, useRef, useState } from 'react';

/**
 * Track the rendered size of an element so SVG charts can lay out in real pixels.
 */
export function useElementSize<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [size, setSize] = useState({ width: 0, height: 0 });

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            setSize({
                width: Math.floor(entry.contentRect.width),
                height: Math.floor(entry.contentRect.height),
            });
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, size] as const;
}

export function useElementWidth<T extends HTMLElement>() {
    const [ref, size] = useElementSize<T>();

    return [ref, size.width] as const;
}
