import { useState } from 'react';
import { MoonIcon, SunIcon } from '@/components/icons';

export function ThemeToggle() {
    const [dark, setDark] = useState(() =>
        typeof document === 'undefined'
            ? false
            : document.documentElement.classList.contains('dark'),
    );

    const toggle = () => {
        const next = !dark;

        document.documentElement.classList.toggle('dark', next);

        try {
            localStorage.setItem('appearance', next ? 'dark' : 'light');
        } catch {
            // Storage may be unavailable; the toggle still applies for this page view.
        }

        setDark(next);
    };

    return (
        <button
            type="button"
            onClick={toggle}
            className="inline-flex size-8 items-center justify-center rounded-lg text-ink-secondary transition hover:bg-line/60 hover:text-ink"
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            {dark ? <SunIcon /> : <MoonIcon />}
        </button>
    );
}
