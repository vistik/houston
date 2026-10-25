<?php

namespace App\Metrics;

/**
 * Every metric a velocity report produces, in the order they are reported.
 */
final class Metrics
{
    /**
     * @var list<class-string<Metric>>
     */
    public const array Definitions = [
        // Pull requests.
        PullRequests\Throughput::class,
        PullRequests\Opened::class,
        PullRequests\MergeFrequency::class,
        PullRequests\CycleTime::class,
        PullRequests\CycleTimeP90::class,
        PullRequests\LeadTime::class,
        PullRequests\CodingTime::class,
        PullRequests\PickupTime::class,
        PullRequests\ReviewTime::class,
        PullRequests\TimeToApproval::class,
        PullRequests\MergeDelay::class,
        PullRequests\PullRequestSize::class,
        PullRequests\MergeRate::class,
        PullRequests\UnreviewedMergeRate::class,
        PullRequests\ChangesRequestedRate::class,
        PullRequests\ReviewDepth::class,
        PullRequests\WaitingOnReviewer::class,
        PullRequests\WaitingOnAuthor::class,
        PullRequests\ReviewRounds::class,
        PullRequests\ReworkRate::class,
        PullRequests\ReviewConcentration::class,
        PullRequests\Remediations::class,
        PullRequests\ActiveDevelopers::class,

        // CI.
        Ci\CiFailureRate::class,
        Ci\CiDefaultBranchFailureRate::class,
        Ci\CiPullRequestFailureRate::class,
        Ci\CiDuration::class,
        Ci\CiDurationP90::class,
        Ci\CiRerunRate::class,
        Ci\CiRuns::class,
        Ci\CiMinutes::class,
        Ci\CiRecoveryTime::class,
        Ci\CiQueueTime::class,
        Ci\CiFlakyRate::class,
        Ci\DefaultBranchRedShare::class,

        // Delivery.
        Delivery\Deployments::class,
        Delivery\DeployFrequency::class,
        Delivery\LeadTimeToProduction::class,
        Delivery\ChangeFailureRate::class,
        Delivery\TimeToRestore::class,
        Delivery\DeploymentFailureRate::class,

        // Right now.
        Snapshot\OpenPullRequests::class,
        Snapshot\StalePullRequests::class,
    ];

    /** @var array<string, Metric>|null */
    private static ?array $all = null;

    /**
     * @return array<string, Metric>
     */
    public static function all(): array
    {
        if (self::$all === null) {
            self::$all = [];

            foreach (self::Definitions as $class) {
                $metric = new $class;
                self::$all[$metric->key()] = $metric;
            }
        }

        return self::$all;
    }

    /**
     * Attach how each metric of a report is calculated. Applied on top of cached reports, so a
     * reworded description shows up straight away.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function describe(array $report): array
    {
        if (! isset($report['kpis']) || ! is_array($report['kpis'])) {
            return $report;
        }

        $metrics = self::all();

        foreach (array_keys($report['kpis']) as $key) {
            if (isset($metrics[$key])) {
                $report['kpis'][$key]['description'] = $metrics[$key]->description();
            }
        }

        return $report;
    }
}
