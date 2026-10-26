import type { ReactNode } from 'react';
import { Icon } from '@/components/icons';
import type { IconProps } from '@/components/icons';
import type { MetricKey } from '@/types';

/**
 * A glyph for every metric, drawn on the same 24 grid and stroke as the icons in `./icons`. Timing
 * metrics lean on clocks, review metrics on the lens, authoring on the pencil, CI on pipelines and
 * gauges, and delivery on the rocket.
 */
const GLYPHS: Record<MetricKey, ReactNode> = {
    // A branch joining main.
    throughput: (
        <>
            <circle cx="6" cy="6" r="2.5" />
            <circle cx="18" cy="18" r="2.5" />
            <path d="M6 8.5V21M6 8.5a9.5 9.5 0 0 0 9.5 9.5" />
        </>
    ),
    // A new branch.
    opened: (
        <>
            <circle cx="6" cy="6" r="2.5" />
            <path d="M6 8.5V21M17 3v8M13 7h8" />
        </>
    ),
    // Many branches landing on one line.
    mergeFrequency: (
        <>
            <circle cx="6" cy="6" r="2" />
            <circle cx="12" cy="6" r="2" />
            <circle cx="18" cy="6" r="2" />
            <path d="M6 8v11M12 8v11M18 8v11M3 19h18" />
        </>
    ),
    // A stopwatch.
    cycleTime: (
        <>
            <circle cx="12" cy="13.5" r="7.5" />
            <path d="M10 2.5h4M12 2.5V6M18.2 6.3l1.3-1.3M12 10v3.5l2.5 2" />
        </>
    ),
    // A distribution with its slow tail marked.
    cycleTimeP90: <path d="M3 20C6 20 6.5 6 9.5 6S15 17 21 19M16 13v7" />,
    // From the first commit to the finish line.
    leadTime: (
        <>
            <circle cx="5" cy="12" r="2.5" />
            <path d="M7.5 12h9M19 8v8" />
        </>
    ),
    // Code being written.
    codingTime: <path d="m8 7-5 5 5 5M16 7l5 5-5 5M13.5 5l-3 14" />,
    // A speech bubble waiting for its first reply.
    pickupTime: (
        <path d="M21 11.5a8.5 8.5 0 0 1-12.4 7.6L3 20.5l2.1-4.7A8.5 8.5 0 1 1 21 11.5ZM9 11.5h.01M12.5 11.5h.01M16 11.5h.01" />
    ),
    // A lens over lines of code.
    reviewTime: (
        <>
            <circle cx="10.5" cy="10.5" r="7" />
            <path d="m15.5 15.5 5 5M7.5 8.5h5.5M7.5 12h3" />
        </>
    ),
    // An approval.
    timeToApproval: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12 3 3 5-6" />
        </>
    ),
    // An hourglass between approval and merge.
    mergeDelay: (
        <path d="M6.5 3h11M6.5 21h11M7.5 3v3.5L12 12l4.5-5.5V3M7.5 21v-3.5L12 12l4.5 5.5V21" />
    ),
    // A diff: lines added and removed.
    pullRequestSize: (
        <>
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <path d="M9 9h6M12 6v6M9 16h6" />
        </>
    ),
    // The merged share of a whole.
    mergeRate: (
        <>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 3.5V12h8.5" />
        </>
    ),
    // Nobody looked.
    unreviewedMergeRate: (
        <>
            <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" />
            <circle cx="12" cy="12" r="3" />
            <path d="m3 3 18 18" />
        </>
    ),
    // A comment raising a problem.
    changesRequestedRate: (
        <path d="M5 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V5a1 1 0 0 1 1-1ZM12 7.5v3.5M12 13.5h.01" />
    ),
    // A thread of comments.
    reviewDepth: (
        <path d="M14 4H4a1 1 0 0 0-1 1v8l3-2h8a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1ZM18 8h2a1 1 0 0 1 1 1v9l-3-2h-7a1 1 0 0 1-1-1v-1" />
    ),
    // A pipeline whose last step failed.
    ciFailureRate: (
        <>
            <circle cx="4.75" cy="12" r="2.75" />
            <circle cx="12" cy="12" r="2.75" />
            <path d="M7.5 12h1.75M14.75 12h1.75m1-2.5 5 5m0-5-5 5" />
        </>
    ),
    // A commit on the default branch that failed.
    ciDefaultBranchFailureRate: (
        <>
            <circle cx="6" cy="12" r="2.5" />
            <path d="M6 3v6.5M6 14.5V21m8-12 6 6m0-6-6 6" />
        </>
    ),
    // A pull request whose build failed.
    ciPullRequestFailureRate: (
        <>
            <circle cx="6" cy="6" r="2.5" />
            <circle cx="6" cy="18" r="2.5" />
            <path d="M6 8.5v7M11.5 6H15a3 3 0 0 1 3 3v2m-3.5 3.5 5 5m0-5-5 5" />
        </>
    ),
    // A gauge.
    ciDuration: <path d="M4 17a8 8 0 1 1 16 0M12 17l3.5-5" />,
    // A gauge pushed into its slow tail.
    ciDurationP90: (
        <path d="M4 17a8 8 0 1 1 16 0M12 17l6-2.5M17 17a5 5 0 0 0-1.46-3.54" />
    ),
    // Running it again.
    ciRerunRate: (
        <path d="M4 12a8 8 0 0 1 14-5.3M20 3.5v4h-4M20 12a8 8 0 0 1-14 5.3M4 20.5v-4h4" />
    ),
    // A run.
    ciRuns: (
        <>
            <rect x="3" y="4" width="18" height="16" rx="3" />
            <path d="M10 9v6l5-3Z" />
        </>
    ),
    // A wall clock.
    ciMinutes: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5h4" />
        </>
    ),
    // A dip and the climb back out.
    ciRecoveryTime: <path d="M3 8c4 0 4 9 8 9s4-11 10-11M17 6h4v4" />,
    // A pull request still pointing at its base.
    openPullRequests: (
        <>
            <circle cx="6" cy="6" r="2.5" />
            <circle cx="6" cy="18" r="2.5" />
            <circle cx="18" cy="18" r="2.5" />
            <path d="M6 8.5v7M11.5 6H15a3 3 0 0 1 3 3v6.5M14 3.5 11.5 6 14 8.5" />
        </>
    ),
    // Gone to sleep.
    stalePullRequests: <path d="M10 8h8l-8 10h8M4 4h4L4 9h4" />,
    // An inbox of jobs waiting for a runner.
    ciQueueTime: (
        <path d="M3 13h5l1.5 3h5l1.5-3h5M5.5 5h13L21 13v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6Z" />
    ),
    // Passed and failed on the same commit.
    ciFlakyRate: <path d="m3 12 3 3 5-6m4-1 6 6m0-6-6 6" />,
    // Passed once it went around again.
    ciFlakyJobRuns: (
        <>
            <path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v4h-4" />
            <path d="m9 12 2 2 4-4" />
        </>
    ),
    // An hourglass, waiting for the re-run.
    ciFlakyJobWait: (
        <path d="M7 3h10M7 21h10M8 3v3a4 4 0 0 0 8 0V3M8 21v-3a4 4 0 0 1 8 0v3" />
    ),
    // A stopwatch crossed out: CI time thrown away.
    ciFlakyJobMinutes: (
        <>
            <circle cx="12" cy="13" r="8" />
            <path d="M10 2h4M12 5v1M8.5 10.5l7 5M15.5 10.5l-7 5" />
        </>
    ),
    // A stretch of the timeline that was red.
    defaultBranchRedShare: (
        <>
            <rect x="3" y="8" width="18" height="8" rx="2" />
            <path d="M10 8v8M15 8v8M10 14l3.5-6M11.5 16l3.5-6" />
        </>
    ),
    // The reviewer's lens, and a clock.
    waitingOnReviewer: (
        <>
            <circle cx="10" cy="9" r="6" />
            <circle cx="17.5" cy="17.5" r="4" />
            <path d="M5.8 13.2 3 16M17.5 15.5v2l1.5 1" />
        </>
    ),
    // The author's pencil, and a clock.
    waitingOnAuthor: (
        <>
            <circle cx="17.5" cy="17.5" r="4" />
            <path d="m12.5 3.5 3 3L7 15H4v-3ZM17.5 15.5v2l1.5 1" />
        </>
    ),
    // Back and forth.
    reviewRounds: <path d="M4 8h16l-3.5-3.5M20 16H4l3.5 3.5" />,
    // Edits after review.
    reworkRate: <path d="m14.5 4.5 5 5L9 20H4v-5ZM12.5 6.5l5 5" />,
    // Reviews converging on one person.
    reviewConcentration: (
        <>
            <circle cx="12" cy="12" r="8.5" />
            <circle cx="12" cy="12" r="4.5" />
            <path d="M12 12h.01" />
        </>
    ),
    // Undo.
    remediations: <path d="M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11" />,
    // People.
    activeDevelopers: (
        <>
            <circle cx="9" cy="8" r="3.5" />
            <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6" />
        </>
    ),
    // A launch.
    deployments: (
        <path d="M9 12c1.8-4.6 5.4-7.8 11-9-1.2 5.6-4.4 9.2-9 11ZM9 12H5.5L8 8h4.5M11 14v4.5l4-2.5v-4.5M6.5 15.5C5 16.5 4.5 19.5 4.5 19.5s3-.5 4-2" />
    ),
    // Launches on a calendar.
    deployFrequency: (
        <>
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M3 10h18M8 3v4M16 3v4M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01" />
        </>
    ),
    // From the first commit to the flag in production.
    leadTimeToProduction: (
        <>
            <circle cx="5" cy="15" r="2.5" />
            <path d="M7.5 15H11M14 21V4h6l-1.5 3 1.5 3h-6" />
        </>
    ),
    // A change that broke something.
    changeFailureRate: <path d="M13 2 4 14h7l-1 8 9-12h-7Z" />,
    // A lifebuoy.
    timeToRestore: (
        <>
            <circle cx="12" cy="12" r="9" />
            <circle cx="12" cy="12" r="4" />
            <path d="m5.6 5.6 3.6 3.6M14.8 14.8l3.6 3.6M14.8 9.2l3.6-3.6M5.6 18.4l3.6-3.6" />
        </>
    ),
    // A deploy that failed.
    deploymentFailureRate: (
        <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5ZM9.5 9.5l5 5m0-5-5 5" />
    ),
};

/** The icon of a metric. */
export function MetricIcon({
    metric,
    ...props
}: IconProps & { metric: MetricKey }) {
    return <Icon {...props}>{GLYPHS[metric]}</Icon>;
}
