import { Head, router } from '@inertiajs/react';
import { NoRepositories } from '@/components/overview-cards';
import {
    LayoutPicker,
    RepositoryOverview,
} from '@/components/overview-layouts';
import type { OverviewLayouts } from '@/components/overview-layouts';
import { PeriodPicker } from '@/components/period-picker';
import { StickyToolbar } from '@/components/ui/sticky-toolbar';
import { useLookbackConfirmation } from '@/hooks/use-lookback-confirmation';
import { usePollWhile } from '@/hooks/use-poll-while';
import { useReloadOnChange } from '@/hooks/use-reload-on-change';
import AppLayout from '@/layouts/app-layout';
import { isCustomPeriod, periodQuery, ReportPeriod } from '@/lib/periods';
import type { PeriodFilters } from '@/lib/periods';
import { wall } from '@/routes';
import { index } from '@/routes/repositories';
import type { RepositoryInfo, RepositorySummary } from '@/types';
import { buttonClass } from '@/components/ui/button';

type Props = {
    filters: PeriodFilters;
    repositories: RepositoryInfo[];
    layouts: OverviewLayouts;
    summaries?: Record<number, RepositorySummary>;
};

export default function RepositoriesIndex({
    filters,
    repositories,
    layouts,
    summaries,
}: Props) {
    const syncing = repositories.some(
        (repository) => repository.sync.inProgress,
    );

    // Poll only the cheap sync state, and fetch summaries again once a sync brings in new data.
    usePollWhile(syncing, ['repositories']);
    useReloadOnChange(
        repositories
            .map(({ id, sync }) => `${id}:${sync.hasData}:${sync.finishedAt}`)
            .join('|'),
        ['summaries'],
    );

    const { choosePeriod, dialog } = useLookbackConfirmation(
        repositories,
        filters,
    );

    return (
        <AppLayout>
            <Head title="Repositories" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Repositories
                </h1>
                <p className="mt-1 text-sm text-ink-secondary">
                    How fast code moves from first commit to merged, across
                    every repository you watch.
                </p>
            </div>

            <StickyToolbar className="mt-3 justify-end">
                <LayoutPicker kind="repositories" layouts={layouts} />
                <a
                    href={wall.url({
                        query: isCustomPeriod(filters)
                            ? {}
                            : { days: filters.days },
                    })}
                    className={buttonClass({
                        variant: 'secondary',
                        size: 'sm',
                    })}
                >
                    Wall display
                </a>
                <PeriodPicker
                    value={filters}
                    onChange={(period) =>
                        choosePeriod(period, () =>
                            router.get(
                                index.url({ query: periodQuery(period) }),
                                {},
                                { preserveScroll: true },
                            ),
                        )
                    }
                />
            </StickyToolbar>
            {dialog}

            {repositories.length === 0 ? (
                <NoRepositories />
            ) : (
                <ReportPeriod value={filters}>
                    <RepositoryOverview
                        className="mt-6"
                        layout={layouts.repositories}
                        repositories={repositories}
                        summaries={summaries}
                        period={filters}
                    />
                </ReportPeriod>
            )}
        </AppLayout>
    );
}
