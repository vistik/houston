import { useEffect, useState } from 'react';

/**
 * Rotates through slides on a timer for the wall displays. Arrow keys step through them and space
 * pauses. The wall is always dark, whatever the app's theme.
 */
export function useWallRotation<T>(slides: T[], rotateSeconds: number) {
    const [current, setCurrent] = useState<T | undefined>(slides[0]);
    const [paused, setPaused] = useState(false);
    const [startedAt, setStartedAt] = useState(() => Date.now());
    const [now, setNow] = useState(() => Date.now());

    const index = Math.max(
        0,
        current === undefined ? 0 : slides.indexOf(current),
    );

    const show = (slide: T) => {
        setCurrent(slide);
        setStartedAt(Date.now());
        window.scrollTo({ top: 0 });
    };

    const go = (offset: number) => {
        if (slides.length === 0) {
            return;
        }

        show(slides[(index + offset + slides.length) % slides.length]);
    };

    useEffect(() => {
        const root = document.documentElement;
        const wasDark = root.classList.contains('dark');

        root.classList.add('dark');

        return () => {
            root.classList.toggle('dark', wasDark);
        };
    }, []);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 250);

        return () => window.clearInterval(timer);
    }, []);

    const elapsed = paused ? 0 : (now - startedAt) / 1000;

    useEffect(() => {
        if (!paused && elapsed >= rotateSeconds) {
            go(1);
        }
    });

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight') {
                go(1);
            } else if (event.key === 'ArrowLeft') {
                go(-1);
            } else if (event.key === ' ') {
                event.preventDefault();
                setPaused((value) => !value);
                setStartedAt(Date.now());
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    return {
        /** The slide on screen; it can drop out of `slides` when the data changes. */
        current: slides[index],
        index,
        show,
        paused,
        progress: paused ? 0 : Math.min(1, elapsed / rotateSeconds),
        now,
    };
}
