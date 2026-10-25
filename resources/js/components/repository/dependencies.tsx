import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import type { BadgeTone } from '@/components/ui/badge';
import { buttonClass } from '@/components/ui/button';
import { Panel } from '@/components/ui/panel';
import { formatDate, timeAgo } from '@/lib/format';
import type { DependencyDrift, DependencyOverview } from '@/types';

const DRIFT_TONES: Partial<Record<DependencyDrift, BadgeTone>> = {
    major: 'bad',
    minor: 'warn',
    patch: 'neutral',
    current: 'good',
};

const PER_PAGE = 10;

/**
 * How far behind a repository's direct dependencies are. Only shown once there are dependencies to check.
 */
export function Dependencies({
    dependencies,
}: {
    dependencies: DependencyOverview;
}) {
    const { earlier, packages } = dependencies;
    const [page, setPage] = useState(1);
    const lastPage = Math.max(1, Math.ceil(packages.length / PER_PAGE));
    // A new check can leave fewer pages than the one being looked at.
    const currentPage = Math.min(page, lastPage);
    const shown = packages.slice(
        (currentPage - 1) * PER_PAGE,
        currentPage * PER_PAGE,
    );

    return (
        <Panel
            title="Dependencies"
            description={`Direct Composer and npm dependencies against the latest stable releases · checked ${timeAgo(dependencies.checkedAt)}`}
            bodyClassName="px-0 pb-2 pt-3"
        >
            <div className="grid grid-cols-2 gap-3 px-5 pb-3 sm:grid-cols-4">
                <Figure
                    label="Outdated"
                    value={`${dependencies.outdated} of ${dependencies.total}`}
                    warn={dependencies.outdated > 0}
                />
                <Figure
                    label="Major versions behind"
                    value={String(dependencies.major)}
                    warn={dependencies.major > 0}
                />
                <Figure
                    label="Minor / patch behind"
                    value={`${dependencies.minor} / ${dependencies.patch}`}
                />
                {earlier && (
                    <Figure
                        label={`Outdated on ${formatDate(earlier.recordedOn)}`}
                        value={String(earlier.outdated)}
                    />
                )}
            </div>
            {dependencies.byConstraint > 0 && (
                <p className="px-5 pb-1 text-[11px] text-ink-muted">
                    {dependencies.byConstraint === dependencies.total
                        ? "There's no lock file saying what is installed"
                        : `${dependencies.byConstraint} aren't in a lock file`}
                    , so their version constraints are checked for whether they
                    allow the latest release: ^2.0 allows every 2.x release, but
                    ^0.2 stops at 0.3.
                </p>
            )}
            {dependencies.unknown > 0 && (
                <p className="px-5 pb-3 text-[11px] text-ink-muted">
                    {dependencies.unknown} more can't be compared: they track a
                    branch or aren't on the public registry.
                </p>
            )}
            {dependencies.outdated === 0 && (
                <p className="px-5 pb-3 text-sm text-good">
                    Every dependency is on its latest release.
                </p>
            )}
            <ul className="divide-y divide-line border-t border-line">
                {shown.map((dependency) => (
                    <li
                        key={`${dependency.ecosystem}:${dependency.name}`}
                        className="flex items-center gap-3 px-5 py-2 text-sm"
                    >
                        <span className="min-w-0 flex-1 truncate">
                            {dependency.name}
                            <span className="ml-2 text-[11px] text-ink-muted">
                                {dependency.ecosystem}
                                {dependency.dev && ' · dev'}
                            </span>
                        </span>
                        <span className="text-xs whitespace-nowrap text-ink-secondary tabular-nums">
                            {dependency.version ?? dependency.constraint}
                            {dependency.drift !== 'current' &&
                                dependency.latestVersion &&
                                ` → ${dependency.latestVersion}`}
                        </span>
                        <Badge tone={DRIFT_TONES[dependency.drift]}>
                            {dependency.drift}
                        </Badge>
                    </li>
                ))}
            </ul>
            {lastPage > 1 && (
                <nav
                    aria-label="Dependency pages"
                    className="flex items-center justify-between gap-3 border-t border-line px-5 pt-2 text-xs text-ink-muted"
                >
                    <span>
                        Page {currentPage} of {lastPage} · {packages.length}{' '}
                        dependencies
                    </span>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={currentPage === 1}
                            onClick={() => setPage(currentPage - 1)}
                            className={buttonClass({
                                variant: 'secondary',
                                size: 'sm',
                            })}
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            disabled={currentPage === lastPage}
                            onClick={() => setPage(currentPage + 1)}
                            className={buttonClass({
                                variant: 'secondary',
                                size: 'sm',
                            })}
                        >
                            Next
                        </button>
                    </div>
                </nav>
            )}
        </Panel>
    );
}

function Figure({
    label,
    value,
    warn = false,
}: {
    label: string;
    value: string;
    warn?: boolean;
}) {
    return (
        <div>
            <div className="text-[11px] text-ink-muted">{label}</div>
            <div
                className={
                    warn
                        ? 'text-lg font-semibold text-warn'
                        : 'text-lg font-semibold'
                }
            >
                {value}
            </div>
        </div>
    );
}
