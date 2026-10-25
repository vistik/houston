<?php

namespace App\Metrics;

use App\Models\Benchmark;
use Illuminate\Support\Collection;

/**
 * Rates metrics against the benchmarks people set: elite, good, medium, or low.
 *
 * Applied on top of cached reports, so a changed benchmark shows up without recomputing anything.
 */
class Benchmarks
{
    private const int Hour = 3600;

    private const int Day = 86400;

    /**
     * Every metric a report produces.
     *
     * @var list<string>
     */
    public const array Metrics = [
        'throughput', 'opened', 'mergeFrequency', 'cycleTime', 'cycleTimeP90', 'leadTime', 'codingTime',
        'pickupTime', 'reviewTime', 'timeToApproval', 'mergeDelay', 'pullRequestSize', 'mergeRate',
        'unreviewedMergeRate', 'changesRequestedRate', 'reviewDepth', 'ciFailureRate', 'ciDefaultBranchFailureRate',
        'ciPullRequestFailureRate', 'ciDuration', 'ciDurationP90', 'ciRerunRate', 'ciRuns', 'ciMinutes',
        'ciRecoveryTime', 'ciQueueTime', 'ciFlakyRate', 'defaultBranchRedShare', 'waitingOnReviewer',
        'waitingOnAuthor', 'reviewRounds', 'reworkRate', 'reviewConcentration', 'remediations', 'deployments',
        'deployFrequency', 'leadTimeToProduction', 'changeFailureRate', 'timeToRestore', 'deploymentFailureRate',
        'openPullRequests', 'stalePullRequests', 'activeDevelopers',
    ];

    /**
     * Counts of work done in the period. They are benchmarked per week so one target fits every period.
     *
     * @var list<string>
     */
    public const array PerWeek = ['throughput', 'opened', 'remediations', 'ciRuns', 'ciMinutes', 'deployments'];

    /**
     * Published industry benchmarks where they exist (DORA, LinearB), and suggested targets otherwise.
     * Metrics that mostly depend on team size or taste have no standard.
     *
     * @var array<string, array{direction: Better, elite: float|int, good: float|int, medium: float|int, source: BenchmarkSource}>
     */
    public const array Standards = [
        // DORA performance clusters.
        'deployFrequency' => ['direction' => Better::Higher, 'elite' => 1, 'good' => 1 / 7, 'medium' => 1 / 30, 'source' => BenchmarkSource::Dora],
        'leadTimeToProduction' => ['direction' => Better::Lower, 'elite' => self::Day, 'good' => 7 * self::Day, 'medium' => 30 * self::Day, 'source' => BenchmarkSource::Dora],
        'changeFailureRate' => ['direction' => Better::Lower, 'elite' => 0.05, 'good' => 0.10, 'medium' => 0.15, 'source' => BenchmarkSource::Dora],
        'timeToRestore' => ['direction' => Better::Lower, 'elite' => self::Hour, 'good' => self::Day, 'medium' => 7 * self::Day, 'source' => BenchmarkSource::Dora],

        // LinearB engineering benchmarks.
        'cycleTime' => ['direction' => Better::Lower, 'elite' => 25 * self::Hour, 'good' => 72 * self::Hour, 'medium' => 161 * self::Hour, 'source' => BenchmarkSource::LinearB],
        'pickupTime' => ['direction' => Better::Lower, 'elite' => self::Hour, 'good' => 4 * self::Hour, 'medium' => 16 * self::Hour, 'source' => BenchmarkSource::LinearB],
        'reviewTime' => ['direction' => Better::Lower, 'elite' => 3 * self::Hour, 'good' => 14 * self::Hour, 'medium' => 24 * self::Hour, 'source' => BenchmarkSource::LinearB],
        'pullRequestSize' => ['direction' => Better::Lower, 'elite' => 100, 'good' => 155, 'medium' => 228, 'source' => BenchmarkSource::LinearB],

        // Suggested.
        'cycleTimeP90' => ['direction' => Better::Lower, 'elite' => 3 * self::Day, 'good' => 7 * self::Day, 'medium' => 14 * self::Day, 'source' => BenchmarkSource::Suggested],
        'leadTime' => ['direction' => Better::Lower, 'elite' => self::Day, 'good' => 3 * self::Day, 'medium' => 7 * self::Day, 'source' => BenchmarkSource::Suggested],
        'codingTime' => ['direction' => Better::Lower, 'elite' => 4 * self::Hour, 'good' => self::Day, 'medium' => 3 * self::Day, 'source' => BenchmarkSource::Suggested],
        'timeToApproval' => ['direction' => Better::Lower, 'elite' => 4 * self::Hour, 'good' => self::Day, 'medium' => 2 * self::Day, 'source' => BenchmarkSource::Suggested],
        'mergeDelay' => ['direction' => Better::Lower, 'elite' => self::Hour, 'good' => 4 * self::Hour, 'medium' => self::Day, 'source' => BenchmarkSource::Suggested],
        'waitingOnReviewer' => ['direction' => Better::Lower, 'elite' => 4 * self::Hour, 'good' => self::Day, 'medium' => 2 * self::Day, 'source' => BenchmarkSource::Suggested],
        'waitingOnAuthor' => ['direction' => Better::Lower, 'elite' => 4 * self::Hour, 'good' => self::Day, 'medium' => 2 * self::Day, 'source' => BenchmarkSource::Suggested],
        'reviewRounds' => ['direction' => Better::Lower, 'elite' => 1, 'good' => 1.5, 'medium' => 2.5, 'source' => BenchmarkSource::Suggested],
        'mergeRate' => ['direction' => Better::Higher, 'elite' => 0.9, 'good' => 0.8, 'medium' => 0.65, 'source' => BenchmarkSource::Suggested],
        'unreviewedMergeRate' => ['direction' => Better::Lower, 'elite' => 0.02, 'good' => 0.05, 'medium' => 0.15, 'source' => BenchmarkSource::Suggested],
        'reviewConcentration' => ['direction' => Better::Lower, 'elite' => 0.3, 'good' => 0.5, 'medium' => 0.7, 'source' => BenchmarkSource::Suggested],
        'ciFailureRate' => ['direction' => Better::Lower, 'elite' => 0.05, 'good' => 0.10, 'medium' => 0.20, 'source' => BenchmarkSource::Suggested],
        'ciDefaultBranchFailureRate' => ['direction' => Better::Lower, 'elite' => 0.02, 'good' => 0.05, 'medium' => 0.10, 'source' => BenchmarkSource::Suggested],
        'ciPullRequestFailureRate' => ['direction' => Better::Lower, 'elite' => 0.10, 'good' => 0.20, 'medium' => 0.30, 'source' => BenchmarkSource::Suggested],
        'ciDuration' => ['direction' => Better::Lower, 'elite' => 5 * 60, 'good' => 10 * 60, 'medium' => 20 * 60, 'source' => BenchmarkSource::Suggested],
        'ciDurationP90' => ['direction' => Better::Lower, 'elite' => 10 * 60, 'good' => 20 * 60, 'medium' => 40 * 60, 'source' => BenchmarkSource::Suggested],
        'ciRerunRate' => ['direction' => Better::Lower, 'elite' => 0.02, 'good' => 0.05, 'medium' => 0.10, 'source' => BenchmarkSource::Suggested],
        'ciRecoveryTime' => ['direction' => Better::Lower, 'elite' => self::Hour, 'good' => 4 * self::Hour, 'medium' => self::Day, 'source' => BenchmarkSource::Suggested],
        'ciQueueTime' => ['direction' => Better::Lower, 'elite' => 30, 'good' => 2 * 60, 'medium' => 5 * 60, 'source' => BenchmarkSource::Suggested],
        'ciFlakyRate' => ['direction' => Better::Lower, 'elite' => 0.01, 'good' => 0.03, 'medium' => 0.05, 'source' => BenchmarkSource::Suggested],
        'defaultBranchRedShare' => ['direction' => Better::Lower, 'elite' => 0.02, 'good' => 0.05, 'medium' => 0.10, 'source' => BenchmarkSource::Suggested],
        'deploymentFailureRate' => ['direction' => Better::Lower, 'elite' => 0.02, 'good' => 0.05, 'medium' => 0.10, 'source' => BenchmarkSource::Suggested],
        'stalePullRequests' => ['direction' => Better::Lower, 'elite' => 0, 'good' => 2, 'medium' => 5, 'source' => BenchmarkSource::Suggested],
    ];

    /** @var Collection<string, Benchmark>|null */
    private ?Collection $benchmarks = null;

    /**
     * Attach a rating to every benchmarked metric of a report.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function apply(array $report, Period $period): array
    {
        if (! isset($report['kpis']) || $this->all()->isEmpty()) {
            return $report;
        }

        foreach ($report['kpis'] as $key => $metric) {
            if ($benchmark = $this->all()->get($key)) {
                $report['kpis'][$key]['benchmark'] = $this->rate($benchmark, $metric['value'], $period->days);
            }
        }

        return $report;
    }

    /**
     * @return Collection<string, Benchmark>
     */
    public function all(): Collection
    {
        return $this->benchmarks ??= Benchmark::all()->keyBy('metric');
    }

    /**
     * @return array{tier: BenchmarkTier|null, direction: Better, elite: float, good: float, medium: float, perWeek: bool}
     */
    public function rate(Benchmark $benchmark, float|int|null $value, int $days): array
    {
        $perWeek = in_array($benchmark->metric, self::PerWeek, true);

        if ($value !== null && $perWeek) {
            $value = $value * 7 / max(1, $days);
        }

        return [
            'tier' => $value === null ? null : self::tier($value, $benchmark->direction, $benchmark->elite, $benchmark->good, $benchmark->medium),
            'direction' => $benchmark->direction,
            'elite' => $benchmark->elite,
            'good' => $benchmark->good,
            'medium' => $benchmark->medium,
            'perWeek' => $perWeek,
        ];
    }

    public static function tier(float|int $value, Better $direction, float $elite, float $good, float $medium): BenchmarkTier
    {
        $meets = fn (float $threshold): bool => $direction === Better::Higher ? $value >= $threshold : $value <= $threshold;

        return match (true) {
            $meets($elite) => BenchmarkTier::Elite,
            $meets($good) => BenchmarkTier::Good,
            $meets($medium) => BenchmarkTier::Medium,
            default => BenchmarkTier::Low,
        };
    }

    /**
     * Replace every benchmark with the given set.
     *
     * @param  array<string, array{direction: Better, elite: float|int, good: float|int, medium: float|int}>  $benchmarks
     */
    public function replace(array $benchmarks): void
    {
        Benchmark::query()->whereNotIn('metric', array_keys($benchmarks))->delete();

        foreach ($benchmarks as $metric => $thresholds) {
            Benchmark::updateOrCreate(['metric' => $metric], [
                'direction' => $thresholds['direction'],
                'elite' => $thresholds['elite'],
                'good' => $thresholds['good'],
                'medium' => $thresholds['medium'],
            ]);
        }

        $this->benchmarks = null;
    }
}
