import { router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { CiView } from '@/components/repository/views/ci-view';
import { DeliveryView } from '@/components/repository/views/delivery-view';
import { FullView } from '@/components/repository/views/full-view';
import { OpenSourceView } from '@/components/repository/views/open-source-view';
import { ReviewView } from '@/components/repository/views/review-view';
import { view as saveView } from '@/routes/repositories';
import type {
    DependencyOverview,
    RepositoryInfo,
    VelocityReport,
} from '@/types';

import type { RepositoryView } from '@/lib/repository-views';

export type { RepositoryView };

export type RepositoryViewProps = {
    report: VelocityReport;
    periodLabel: string;
    defaultBranch: string | null;
    repository: RepositoryInfo;
    dependencies: DependencyOverview | null;
};

/**
 * Every view of a repository, in the order the switch lists them. A new view needs a case in the enum,
 * its name in the type above and an entry here.
 */
export const REPOSITORY_VIEWS: Record<
    RepositoryView,
    {
        label: string;
        title: string;
        component: ComponentType<RepositoryViewProps>;
    }
> = {
    full: {
        label: 'Full',
        title: 'Everything Houston measures',
        component: FullView,
    },
    'open-source': {
        label: 'Open source',
        title: 'Contributions, how fast they get a response, and releases',
        component: OpenSourceView,
    },
    delivery: {
        label: 'Delivery',
        title: 'DORA metrics and what feeds them',
        component: DeliveryView,
    },
    review: {
        label: 'Code review',
        title: 'How long pull requests wait on reviewers, and how reviews go',
        component: ReviewView,
    },
    ci: {
        label: 'CI',
        title: 'Failures, red time, speed and flakiness of the pipeline',
        component: CiView,
    },
};

/** The views as options to pick from, in the order of `REPOSITORY_VIEWS`. */
export const REPOSITORY_VIEW_OPTIONS = (
    Object.entries(REPOSITORY_VIEWS) as [
        RepositoryView,
        (typeof REPOSITORY_VIEWS)[RepositoryView],
    ][]
).map(([value, { label, title }]) => ({ value, label, title }));

/**
 * Show a repository in another view. The page switches right away and the choice is saved for that
 * repository.
 */
export function switchRepositoryView(
    repository: RepositoryInfo,
    view: RepositoryView,
) {
    router
        .optimistic<{ view: RepositoryView }>(() => ({ view }))
        .put(
            saveView.url(repository.id),
            { view },
            { only: ['view'], preserveScroll: true, preserveState: true },
        );
}
