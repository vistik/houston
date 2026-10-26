import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ThemeToggle } from '@/components/theme-toggle';
import { WatchRepositoryButton } from '@/components/watch-repository';
import { Wordmark } from '@/components/wordmark';
import { cn } from '@/lib/utils';
import { home, logout, settings } from '@/routes';
import { index as repositories } from '@/routes/repositories';
import { index as teams } from '@/routes/teams';

export default function AppLayout({
    children,
    nav,
}: {
    children: ReactNode;
    nav?: ReactNode;
}) {
    const { url, props } = usePage();
    const inSettings = url.startsWith('/settings');
    const inTeams = url.startsWith('/teams');
    const inRepositories = /^\/repos(\/|\?|$)/.test(url);

    return (
        <div className="relative isolate min-h-screen">
            <div
                aria-hidden="true"
                className="page-backdrop pointer-events-none absolute inset-x-0 top-0 -z-10 h-96"
            />
            {props.demo && (
                <div className="border-b border-line bg-surface px-4 py-1.5 text-center text-xs text-ink-secondary">
                    You're looking at a read-only demo of Houston. Feel free to
                    poke around.
                </div>
            )}
            <header className="sticky top-0 z-20 border-b border-line bg-page/80 backdrop-blur-md">
                <div className="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4 sm:px-6">
                    <Link href={home()} className="shrink-0">
                        <Wordmark />
                    </Link>
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        {nav}
                    </div>
                    <WatchRepositoryButton />
                    <Link
                        href={repositories()}
                        className={cn(
                            'text-xs font-medium transition hover:text-ink',
                            inRepositories ? 'text-ink' : 'text-ink-secondary',
                        )}
                    >
                        Repositories
                    </Link>
                    <Link
                        href={teams()}
                        className={cn(
                            'text-xs font-medium transition hover:text-ink',
                            inTeams ? 'text-ink' : 'text-ink-secondary',
                        )}
                    >
                        Teams
                    </Link>
                    {!props.demo && (
                        <Link
                            href={settings()}
                            className={cn(
                                'text-xs font-medium transition hover:text-ink',
                                inSettings ? 'text-ink' : 'text-ink-secondary',
                            )}
                        >
                            Settings
                        </Link>
                    )}
                    <ThemeToggle />
                    {props.auth.user && (
                        <Link
                            href={logout()}
                            as="button"
                            className="text-xs font-medium text-ink-secondary transition hover:text-ink"
                        >
                            Log out
                        </Link>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                {children}
            </main>
        </div>
    );
}
