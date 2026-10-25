/** The views a repository page can show, matching `App\Enums\RepositoryView`. */
export type RepositoryView =
    | 'full'
    | 'open-source'
    | 'delivery'
    | 'review'
    | 'ci';

/** What a repository's card can show, matching `App\Enums\RepositoryCardView`. */
export type RepositoryCardView =
    | 'full'
    | 'open-source'
    | 'delivery'
    | 'review'
    | 'ci';

/** What a team's card can show, matching `App\Enums\TeamCardView`. */
export type TeamCardView = 'full' | 'review' | 'throughput';

/** A metric the summaries behind every card carry, for repositories and teams alike. */
export type TeamSummaryMetric =
    | 'throughput'
    | 'opened'
    | 'cycleTime'
    | 'pickupTime'
    | 'openPullRequests';

/** A metric the summaries behind a repository's card carry. */
export type SummaryMetric =
    | TeamSummaryMetric
    | 'ciFailureRate'
    | 'deployments'
    | 'changeFailureRate';

/** A slide of a repository's own wall. */
export type WallSlide =
    | 'cycleTime'
    | 'pickupTime'
    | 'reviewTime'
    | 'throughput'
    | 'ciFailureRate'
    | 'deployments'
    | 'stages'
    | 'waiting';

type MetricPick<Metric extends string = SummaryMetric> = {
    metric: Metric;
    label: string;
};
type Four<Metric extends string = SummaryMetric> = [
    MetricPick<Metric>,
    MetricPick<Metric>,
    MetricPick<Metric>,
    MetricPick<Metric>,
];

/**
 * What each card of a repository shows: its four numbers on the front page and the repository list, and
 * on its tile on the wall. Change a list to change a card; a new card needs a case in the enum, its name
 * in the type above and an entry here.
 */
export const REPOSITORY_CARD_VIEWS: Record<
    RepositoryCardView,
    { label: string; card: Four; wallTile: Four }
> = {
    full: {
        label: 'Full',
        card: [
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'ciFailureRate', label: 'CI failure rate' },
        ],
        wallTile: [
            { metric: 'cycleTime', label: 'To merge' },
            { metric: 'pickupTime', label: 'Pickup' },
            { metric: 'throughput', label: 'Merged' },
            { metric: 'ciFailureRate', label: 'CI fails' },
        ],
    },
    'open-source': {
        label: 'Open source',
        card: [
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'pickupTime', label: 'First response' },
            { metric: 'opened', label: 'Opened PRs' },
            { metric: 'deployments', label: 'Releases' },
        ],
        wallTile: [
            { metric: 'throughput', label: 'Merged' },
            { metric: 'pickupTime', label: 'Response' },
            { metric: 'opened', label: 'Opened' },
            { metric: 'deployments', label: 'Releases' },
        ],
    },
    delivery: {
        label: 'Delivery',
        card: [
            { metric: 'deployments', label: 'Deploys' },
            { metric: 'changeFailureRate', label: 'Needed a fix' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'ciFailureRate', label: 'CI failure rate' },
        ],
        wallTile: [
            { metric: 'deployments', label: 'Deploys' },
            { metric: 'changeFailureRate', label: 'Fixes' },
            { metric: 'cycleTime', label: 'To merge' },
            { metric: 'ciFailureRate', label: 'CI fails' },
        ],
    },
    review: {
        label: 'Code review',
        card: [
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'openPullRequests', label: 'Open PRs' },
            { metric: 'opened', label: 'Opened PRs' },
        ],
        wallTile: [
            { metric: 'pickupTime', label: 'Pickup' },
            { metric: 'cycleTime', label: 'To merge' },
            { metric: 'openPullRequests', label: 'Open' },
            { metric: 'opened', label: 'Opened' },
        ],
    },
    ci: {
        label: 'CI',
        card: [
            { metric: 'ciFailureRate', label: 'CI failure rate' },
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'deployments', label: 'Deploys' },
        ],
        wallTile: [
            { metric: 'ciFailureRate', label: 'CI fails' },
            { metric: 'throughput', label: 'Merged' },
            { metric: 'cycleTime', label: 'To merge' },
            { metric: 'deployments', label: 'Deploys' },
        ],
    },
};

/** What each card of a team shows on the front page and the team list. */
export const TEAM_CARD_VIEWS: Record<
    TeamCardView,
    { label: string; card: Four<TeamSummaryMetric> }
> = {
    full: {
        label: 'Full',
        card: [
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'openPullRequests', label: 'Open PRs' },
        ],
    },
    review: {
        label: 'Review',
        card: [
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'openPullRequests', label: 'Waiting PRs' },
            { metric: 'opened', label: 'Opened PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
        ],
    },
    throughput: {
        label: 'Throughput',
        card: [
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'opened', label: 'Opened PRs' },
            { metric: 'openPullRequests', label: 'Open PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
        ],
    },
};

/**
 * What each view of a repository page shows on the wall: the four numbers of its slide on the wall of all
 * repositories, and the slides of its own wall, in order.
 */
export const VIEW_METRICS: Record<
    RepositoryView,
    { wallSlide: Four; wallSlides: WallSlide[] }
> = {
    full: {
        wallSlide: [
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'ciFailureRate', label: 'CI failure rate' },
        ],
        wallSlides: [
            'cycleTime',
            'pickupTime',
            'reviewTime',
            'throughput',
            'ciFailureRate',
            'deployments',
            'stages',
            'waiting',
        ],
    },
    'open-source': {
        wallSlide: [
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'pickupTime', label: 'First response' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'deployments', label: 'Releases' },
        ],
        wallSlides: [
            'throughput',
            'pickupTime',
            'cycleTime',
            'deployments',
            'waiting',
        ],
    },
    delivery: {
        wallSlide: [
            { metric: 'deployments', label: 'Deploys' },
            { metric: 'changeFailureRate', label: 'Needed a fix' },
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
        ],
        wallSlides: ['deployments', 'throughput', 'cycleTime', 'stages'],
    },
    review: {
        wallSlide: [
            { metric: 'pickupTime', label: 'Pickup time' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'openPullRequests', label: 'Open PRs' },
            { metric: 'opened', label: 'Opened PRs' },
        ],
        wallSlides: [
            'pickupTime',
            'reviewTime',
            'cycleTime',
            'stages',
            'waiting',
        ],
    },
    ci: {
        wallSlide: [
            { metric: 'ciFailureRate', label: 'CI failure rate' },
            { metric: 'throughput', label: 'Merged PRs' },
            { metric: 'cycleTime', label: 'Time to merge' },
            { metric: 'deployments', label: 'Deploys' },
        ],
        wallSlides: ['ciFailureRate', 'throughput', 'cycleTime', 'deployments'],
    },
};

/** The wall metrics of a view, falling back to the full view for one the front end doesn't know. */
export function viewMetrics(view: string | undefined) {
    return VIEW_METRICS[view as RepositoryView] ?? VIEW_METRICS.full;
}

/** A repository card, falling back to the full one for one the front end doesn't know. */
export function repositoryCard(view: string | undefined) {
    return (
        REPOSITORY_CARD_VIEWS[view as RepositoryCardView] ??
        REPOSITORY_CARD_VIEWS.full
    );
}

/** A team card, falling back to the full one for one the front end doesn't know. */
export function teamCard(view: string | undefined) {
    return TEAM_CARD_VIEWS[view as TeamCardView] ?? TEAM_CARD_VIEWS.full;
}

/** The cards of a kind as options to pick from. */
export function cardOptions<View extends string>(
    views: Record<View, { label: string }>,
): { value: View; label: string }[] {
    return (Object.keys(views) as View[]).map((value) => ({
        value,
        label: views[value].label,
    }));
}
