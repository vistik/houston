<?php

namespace App\Metrics;

/**
 * Every trend a velocity report charts, in the order they are shown.
 */
final class Trends
{
    /**
     * @var list<class-string<Trend>>
     */
    public const array Definitions = [
        Trends\ThroughputTrend::class,
        Trends\OpenPullRequestsTrend::class,
        Trends\TimeToMergeTrend::class,
        Trends\PickupTimeTrend::class,
        Trends\CiFailureRateTrend::class,
        Trends\DeploymentsTrend::class,
    ];

    /**
     * The trends of a team report, which charts no CI runs or deployments.
     *
     * @var list<class-string<Trend>>
     */
    public const array PullRequestTrends = [
        Trends\ThroughputTrend::class,
        Trends\OpenPullRequestsTrend::class,
        Trends\TimeToMergeTrend::class,
        Trends\PickupTimeTrend::class,
    ];

    /**
     * Attach what each trend shows and how it is calculated. Applied on top of cached reports, so a
     * reworded description shows up straight away.
     *
     * @param  array<string, mixed>  $report
     * @param  list<class-string<Trend>>  $definitions  The trends the report charts.
     * @return array<string, mixed>
     */
    public static function describe(array $report, array $definitions = self::Definitions): array
    {
        $report['trends'] = [];

        foreach ($definitions as $class) {
            $trend = new $class;

            $report['trends'][$trend->key()] = [
                'title' => $trend->title(),
                'summary' => $trend->summary(),
                'description' => $trend->description(),
                'unit' => $trend->unit()->value,
            ];
        }

        return $report;
    }
}
