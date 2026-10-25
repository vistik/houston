import { Head, Link, router, usePage } from '@inertiajs/react';
import { NoTeams } from '@/components/overview-cards';
import { LayoutPicker, TeamOverview } from '@/components/overview-layouts';
import type { OverviewLayouts } from '@/components/overview-layouts';
import { PeriodPicker } from '@/components/period-picker';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import AppLayout from '@/layouts/app-layout';
import { periodQuery, ReportPeriod } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { index as teamSettings } from '@/routes/settings/teams';
import { index } from '@/routes/teams';
import type { TeamInfo, TeamSummary } from '@/types';
import { buttonClass } from '@/components/ui/button';

type Props = {
    filters: PeriodFilters;
    teams: TeamInfo[];
    layouts: OverviewLayouts;
    summaries?: Record<number, TeamSummary>;
};

export default function TeamsIndex({
    filters,
    teams,
    layouts,
    summaries,
}: Props) {
    const { demo } = usePage().props;

    return (
        <AppLayout>
            <Head title="Teams" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">Teams</h1>
                <p className="mt-1 text-sm text-ink-secondary">
                    How fast each team&apos;s pull requests move, across every
                    repository you watch.
                </p>
            </div>

            <StickyToolbar className="mt-3 justify-end">
                <LayoutPicker kind="teams" layouts={layouts} />
                {!demo && (
                    <Link
                        href={teamSettings()}
                        className={buttonClass({
                            variant: 'secondary',
                            size: 'sm',
                        })}
                    >
                        Manage teams
                    </Link>
                )}
                <PeriodPicker
                    value={filters}
                    onChange={(period) =>
                        router.get(
                            index.url({ query: periodQuery(period) }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                />
            </StickyToolbar>

            {teams.length === 0 ? (
                <NoTeams />
            ) : (
                <ReportPeriod value={filters}>
                    <TeamOverview
                        className="mt-6"
                        layout={layouts.teams}
                        teams={teams}
                        summaries={summaries}
                        period={filters}
                    />
                </ReportPeriod>
            )}
        </AppLayout>
    );
}
