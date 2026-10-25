import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { edit as benchmarks } from '@/routes/settings/benchmarks';
import { edit as defaults } from '@/routes/settings/defaults';
import { index as events } from '@/routes/settings/events';
import { index as logs } from '@/routes/settings/log';
import { index as syncs } from '@/routes/settings/syncs';
import { index as teams } from '@/routes/settings/teams';

const TABS = [
    { label: 'Syncs', href: syncs().url },
    { label: 'Teams', href: teams().url },
    { label: 'Events', href: events().url },
    { label: 'Defaults', href: defaults().url },
    { label: 'Benchmarks', href: benchmarks().url },
    { label: 'Log', href: logs().url },
];

/**
 * The settings pages, each its own tab so it keeps its own URL and refreshes on its own.
 */
export default function SettingsLayout({ children }: { children: ReactNode }) {
    const { url } = usePage();
    const path = url.split('?')[0];

    return (
        <AppLayout>
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Settings
                </h1>
                <p className="mt-1 text-sm text-ink-secondary">
                    Mission parameters: how Houston keeps its GitHub cache
                    fresh, how reports open, what it rates your metrics against,
                    and what it has been up to.
                </p>
            </div>

            <nav
                aria-label="Settings"
                className="mt-6 mb-6 flex gap-5 overflow-x-auto shadow-[inset_0_-1px_0_var(--color-line)]"
            >
                {TABS.map((tab) => {
                    const active = path === tab.href;

                    return (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'shrink-0 border-b-2 pb-2.5 text-sm font-medium whitespace-nowrap transition',
                                active
                                    ? 'border-ink text-ink'
                                    : 'border-transparent text-ink-secondary hover:text-ink',
                            )}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
            </nav>

            {children}
        </AppLayout>
    );
}
