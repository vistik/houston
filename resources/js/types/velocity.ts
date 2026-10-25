import type {
    RepositoryCardView,
    RepositoryView,
    SummaryMetric,
    TeamCardView,
    TeamSummaryMetric,
} from '@/lib/repository-views';
export type MetricUnit = 'count' | 'duration' | 'ratio' | 'lines' | 'perDay';

export type Better = 'higher' | 'lower' | 'neutral';

export type BenchmarkTier = 'elite' | 'good' | 'medium' | 'low';

export type Thresholds = {
    direction: 'higher' | 'lower';
    elite: number;
    good: number;
    medium: number;
};

/**
 * Where a metric lands against the benchmark set for it. Per-week benchmarks rate period counts by their weekly rate.
 */
export type MetricBenchmark = Thresholds & {
    tier: BenchmarkTier | null;
    perWeek: boolean;
};

export type Metric = {
    value: number | null;
    previous: number | null;
    unit: MetricUnit;
    better: Better;
    /** A metric of right now, such as open pull requests, with no previous value to compare with. */
    snapshot?: true;
    benchmark?: MetricBenchmark;
    /** How the metric is calculated. */
    description?: string;
    /** For metrics that sum up one value per item, like cycle time per merged pull request: which statistic `value` is. */
    statistic?: MetricStatistic;
    /** The median of those values this period. */
    median?: number | null;
    /** The average of those values this period. */
    average?: number | null;
};

export type MetricStatistic = 'median' | 'average';

export type MetricKey =
    | 'throughput'
    | 'opened'
    | 'mergeFrequency'
    | 'cycleTime'
    | 'cycleTimeP90'
    | 'leadTime'
    | 'codingTime'
    | 'pickupTime'
    | 'reviewTime'
    | 'timeToApproval'
    | 'mergeDelay'
    | 'pullRequestSize'
    | 'mergeRate'
    | 'unreviewedMergeRate'
    | 'changesRequestedRate'
    | 'reviewDepth'
    | 'ciFailureRate'
    | 'ciDefaultBranchFailureRate'
    | 'ciPullRequestFailureRate'
    | 'ciDuration'
    | 'ciDurationP90'
    | 'ciRerunRate'
    | 'ciRuns'
    | 'ciMinutes'
    | 'ciRecoveryTime'
    | 'openPullRequests'
    | 'stalePullRequests'
    | 'ciQueueTime'
    | 'ciFlakyRate'
    | 'defaultBranchRedShare'
    | 'waitingOnReviewer'
    | 'waitingOnAuthor'
    | 'reviewRounds'
    | 'reworkRate'
    | 'reviewConcentration'
    | 'remediations'
    | 'activeDevelopers'
    | 'deployments'
    | 'deployFrequency'
    | 'leadTimeToProduction'
    | 'changeFailureRate'
    | 'timeToRestore'
    | 'deploymentFailureRate';

/** Metrics calculated from pull requests alone, which team reports carry too. */
export type PullRequestMetricKey = Exclude<
    MetricKey,
    | 'ciFailureRate'
    | 'ciDefaultBranchFailureRate'
    | 'ciPullRequestFailureRate'
    | 'ciDuration'
    | 'ciDurationP90'
    | 'ciRerunRate'
    | 'ciRuns'
    | 'ciMinutes'
    | 'ciRecoveryTime'
    | 'ciQueueTime'
    | 'ciFlakyRate'
    | 'defaultBranchRedShare'
    | 'deployments'
    | 'deployFrequency'
    | 'leadTimeToProduction'
    | 'changeFailureRate'
    | 'timeToRestore'
    | 'deploymentFailureRate'
>;

export type SyncState = {
    status: 'pending' | 'queued' | 'running' | 'succeeded' | 'failed';
    inProgress: boolean;
    hasData: boolean;
    finishedAt: string | null;
    error: string | null;
};

export type SyncPhaseKey =
    | 'details'
    | 'pull_requests'
    | 'open_pull_requests'
    | 'workflow_runs'
    | 'deployments';

export type SyncPhase = {
    key: SyncPhaseKey;
    label: string;
    state: 'done' | 'active' | 'failed' | 'pending' | 'skipped';
    progress: number | null;
    startedAt: string | null;
};

export type SyncProgress = {
    status: SyncState['status'];
    inProgress: boolean;
    stalled: boolean;
    backfill: boolean;
    phase: SyncPhaseKey | null;
    phases: SyncPhase[];
    progress: number | null;
    estimatedFinishAt: string | null;
    pullRequestsFetched: number;
    workflowRunsFetched: number;
    historyFrom: string | null;
    rateLimitedUntil: string | null;
    queuedAt: string | null;
    startedAt: string | null;
    lastActivityAt: string | null;
    finishedAt: string | null;
    lastDurationSeconds: number | null;
    error: string | null;
};

export type RateLimit = {
    resource: string;
    label: string;
    limit: number;
    remaining: number;
    used: number;
    resetAt: string;
    blockedUntil: string | null;
};

export type RateLimits = {
    reserve: number;
    observedAt: string | null;
    error: string | null;
    resources: RateLimit[];
};

export type RepositorySyncOverview = Pick<
    RepositoryInfo,
    'id' | 'owner' | 'name' | 'fullName' | 'avatarUrl'
> & {
    lookbackDays: number | null;
    queuePosition: number | null;
    sync: SyncProgress;
};

export type RepositoryInfo = {
    id: number;
    owner: string;
    name: string;
    fullName: string;
    url: string;
    description: string | null;
    avatarUrl: string | null;
    defaultBranch: string | null;
    /** Null until a sync has fetched the repository's visibility. */
    isPrivate: boolean | null;
    excludedWorkflowIds: number[];
    /** The view its page opens in, which also decides what its card and wall show. */
    view: RepositoryView;
    /** What its card on the front page, the repository list and the wall shows. */
    cardView: RepositoryCardView;
    lookback: RepositoryLookback;
    sync: SyncState;
};

/**
 * How far back the synced history of a repository reaches: `days` before today, from the `from` date on.
 * `coveredFrom` is where the cached data starts, later than `from` until a backfill finishes, or null before the first sync.
 */
export type RepositoryLookback = {
    days: number;
    from: string;
    allTime: boolean;
    coveredFrom: string | null;
};

export type PullRequestTrendPoint = {
    date: string;
    merged: number;
    opened: number;
    /** Pull requests open and ready for review at the end of the bucket, or now for the bucket still filling up. */
    open: number;
    /** Draft pull requests open at the end of the bucket, or now for the bucket still filling up. */
    openDrafts: number;
    cycleTime: number | null;
    pickupTime: number | null;
};

export type TrendPoint = PullRequestTrendPoint & {
    deployments: number;
    ciRuns: number;
    ciFailureRate: number | null;
};

/** The trends charted from pull requests alone, which team reports carry too. */
export type PullRequestTrendKey =
    | 'throughput'
    | 'openPullRequests'
    | 'timeToMerge'
    | 'pickupTime';

export type TrendKey = PullRequestTrendKey | 'ciFailureRate' | 'deployments';

/** What a chart in the trends section shows, and how each point is calculated. */
export type Trend = {
    title: string;
    summary: string;
    description: string;
    unit: MetricUnit;
};

export type CycleStage = {
    stage: 'coding' | 'pickup' | 'author' | 'review' | 'merge';
    label: string;
    seconds: number | null;
};

export type SizeBucket = {
    label: string;
    max: number | null;
    count: number;
    cycleTime: number | null;
};

export type ReviewStatus =
    | 'draft'
    | 'awaiting_review'
    | 'waiting_on_reviewer'
    | 'waiting_on_author'
    | 'approved';

export type OpenPullRequest = {
    /** The owner/name of its repository. */
    repository: string;
    number: number;
    title: string;
    url: string;
    author: string | null;
    authorAvatarUrl: string | null;
    status: ReviewStatus;
    checks: string | null;
    size: number;
    sizeLabel: string;
    openedAt: string;
    ageSeconds: number;
    waitingSeconds: number | null;
    rounds: number;
    lastActivityAt: string | null;
    isStale: boolean;
    reviewers: string[];
};

export type WorkflowStats = {
    workflowId: number;
    name: string;
    excluded: boolean;
    runs: number;
    failureRate: number | null;
    defaultBranchFailureRate: number | null;
    rerunRate: number | null;
    flakyRate: number | null;
    queueTime: number | null;
    duration: number | null;
    durationP90: number | null;
    minutes: number;
    lastConclusion: string;
    lastUrl: string;
};

export type MergedPullRequest = {
    /** The owner/name of its repository. */
    repository: string;
    number: number;
    title: string;
    url: string;
    author: string | null;
    authorAvatarUrl: string | null;
    cycleTime: number;
    pickupTime: number | null;
    size: number;
    sizeLabel: string;
    mergedAt: string;
};

export type DefaultBranchStatus = {
    state: 'passing' | 'failing' | 'unknown';
    failingWorkflows: string[];
    brokenSince: string | null;
    lastRunAt: string | null;
};

export type DeploySource = 'deployments' | 'releases' | null;

export type Delivery = {
    source: DeploySource;
    recent: {
        name: string;
        ref: string | null;
        url: string | null;
        deployedAt: string;
        shipped: number;
        failedChange: boolean;
    }[];
    pending: { count: number; oldestSeconds: number | null };
};

export type Remediation = {
    /** The owner/name of its repository. */
    repository: string;
    number: number;
    title: string;
    url: string;
    author: string | null;
    authorAvatarUrl: string | null;
    mergedAt: string;
};

export type BotBacklog = {
    open: number;
    oldestSeconds: number | null;
    medianAgeSeconds: number | null;
    merged: number;
    cycleTime: number | null;
    oldest: {
        number: number;
        title: string;
        url: string;
        author: string | null;
        ageSeconds: number;
    }[];
};

/**
 * What every report carries: everything calculated from pull requests. A team report is only this, since CI runs and
 * deployments belong to repositories rather than people.
 */
export type PullRequestReport = {
    period: {
        days: number;
        custom: boolean;
        from: string;
        to: string;
        bucketDays: number;
        granularity: 'day' | 'week' | 'month';
    };
    kpis: Record<PullRequestMetricKey, Metric>;
    cycleBreakdown: CycleStage[];
    cycleDistribution: { label: string; count: number }[];
    trend: PullRequestTrendPoint[];
    trends: Record<PullRequestTrendKey, Trend>;
    /**
     * Every pull request metric per trend bucket, how many pull requests were open at the end of each,
     * and for repositories every delivery metric too. CI metrics are only in the trend.
     */
    series: Partial<Record<MetricKey, (number | null)[]>>;
    sizes: SizeBucket[];
    workInProgress: OpenPullRequest[];
    slowestMerged: MergedPullRequest[];
    remediations: Remediation[];
    workingTime: string;
};

/** Which workflow runs the CI failure rate counts, as set in the report defaults. */
export type CiFailureRateScope = 'default_branch' | 'all_branches';

export type VelocityReport = Omit<
    PullRequestReport,
    'kpis' | 'trend' | 'trends'
> & {
    kpis: Record<MetricKey, Metric>;
    trend: TrendPoint[];
    trends: Record<TrendKey, Trend>;
    workflows: WorkflowStats[];
    ciFailureRateScope: CiFailureRateScope;
    defaultBranchStatus: DefaultBranchStatus;
    delivery: Delivery;
    botBacklog: BotBacklog;
};

export type TeamReport = PullRequestReport & {
    /** How many pull requests the team opened or merged in the period, by repository owner/name. */
    repositories: Record<string, number>;
};

export type TeamMembership = {
    id: number;
    login: string;
    avatarUrl: string | null;
    /** The first day on the team, or null when it always was. */
    joinedOn: string | null;
    /** The first day no longer on the team, or null when it still is. */
    leftOn: string | null;
    current: boolean;
};

export type TeamInfo = {
    id: number;
    name: string;
    slug: string;
    /** What its card on the front page and the team list shows. */
    cardView: TeamCardView;
    memberships: TeamMembership[];
};

/** Something that changed how people work, marked on every timeline. */
export type TimelineEvent = {
    id: number;
    title: string;
    /** The day it happened, as YYYY-MM-DD. */
    date: string;
};

/** The event a report compares before and after, with the day the synced history starts. */
export type FocusedTimelineEvent = TimelineEvent & {
    historyFrom: string;
};

export type TeamSummary = {
    kpis: Pick<
        Record<PullRequestMetricKey, Metric>,
        TeamSummaryMetric | 'stalePullRequests'
    >;
    trend: Pick<TrendPoint, 'date' | 'merged'>[];
    period: SummaryPeriod;
    /** The summary's metrics per trend bucket, for those that have them. */
    series: Partial<Record<PullRequestMetricKey, (number | null)[]>>;
};

/** How a summary's trend is grouped. */
export type SummaryPeriod = Pick<
    PullRequestReport['period'],
    'to' | 'bucketDays' | 'granularity'
>;

export type RepositorySummary = {
    kpis: Pick<Record<MetricKey, Metric>, SummaryMetric | 'stalePullRequests'>;
    trend: Pick<TrendPoint, 'date' | 'merged'>[];
    period: SummaryPeriod;
    /** The summary's metrics per trend bucket, for those that have them. */
    series: Partial<Record<MetricKey, (number | null)[]>>;
};

export type DependencyDrift =
    | 'current'
    | 'patch'
    | 'minor'
    | 'major'
    | 'unknown';

/** How far behind the direct Composer and npm dependencies on the default branch are. */
export type DependencyOverview = {
    checkedAt: string;
    total: number;
    outdated: number;
    major: number;
    minor: number;
    patch: number;
    /** Branches, private packages, and packages missing from the registry. */
    unknown: number;
    /** Compared by their version constraint, since no lock file says what is installed. */
    byConstraint: number;
    /** The check about a month earlier, to compare with. */
    earlier: { recordedOn: string; outdated: number; major: number } | null;
    /** The furthest behind first. */
    packages: {
        ecosystem: 'composer' | 'npm';
        name: string;
        version: string | null;
        constraint: string;
        latestVersion: string | null;
        drift: DependencyDrift;
        dev: boolean;
    }[];
};
