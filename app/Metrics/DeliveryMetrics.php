<?php

namespace App\Metrics;

use App\Metrics\Delivery\DeliveryWindow;
use App\Models\Deployment;
use App\Models\PullRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * DORA delivery metrics: deploy frequency, lead time to production, change failure rate and time to restore.
 *
 * A deployment ships every pull request merged into its branch since the previous deployment of that
 * branch, up to its deployed commit. A deployment is a failed change when the deployment after it ships
 * a revert or hotfix.
 */
final class DeliveryMetrics
{
    /** @var Collection<int, array{deployment: Deployment, shipped: Collection<int, PullRequest>, failedChange: bool, restoredAt: CarbonImmutable|null}> */
    private Collection $releases;

    /** @var array<string, true> */
    private array $knownBranches;

    /**
     * @param  Collection<int, Deployment>  $deployments
     * @param  Collection<int, PullRequest>  $pullRequests
     */
    public function __construct(
        private Collection $deployments,
        private Collection $pullRequests,
        private ?string $defaultBranch,
        private WorkingTime $time,
    ) {
        $this->knownBranches = array_fill_keys($pullRequests->pluck('base_ref')->filter()->unique()->values()->all(), true);
        $this->releases = $this->buildReleases();
    }

    /**
     * The deployments within a window, for metrics to calculate from.
     *
     * @param  callable(?CarbonImmutable): bool  $inWindow
     */
    public function window(callable $inWindow, int $days): DeliveryWindow
    {
        return new DeliveryWindow(
            releases: $this->releases->filter(fn (array $release): bool => $inWindow($release['deployment']->deployed_at)),
            attempts: $this->deployments->filter(fn (Deployment $deployment): bool => $inWindow($deployment->deployed_at)),
            hasSignal: $this->deployments->isNotEmpty(),
            days: $days,
            time: $this->time,
        );
    }

    /**
     * Successful deployments per bucket.
     *
     * @return array<int, int>
     */
    public function trend(Period $period): array
    {
        $counts = array_fill(0, count($period->buckets()), 0);

        foreach ($this->releases as $release) {
            if ($period->charts($release['deployment']->deployed_at)) {
                $counts[$period->bucketIndex($release['deployment']->deployed_at)]++;
            }
        }

        return $counts;
    }

    /**
     * The deployments within each trend bucket, sorted in one pass instead of one pass per bucket.
     *
     * @param  list<int>  $days  How long each bucket is.
     * @return list<DeliveryWindow>
     */
    public function bucketWindows(Period $period, array $days): array
    {
        $releases = array_fill(0, count($days), []);
        $attempts = $releases;

        foreach ($this->releases as $key => $release) {
            if ($period->charts($release['deployment']->deployed_at)) {
                $releases[$period->bucketIndex($release['deployment']->deployed_at)][$key] = $release;
            }
        }

        foreach ($this->deployments as $key => $deployment) {
            if ($period->charts($deployment->deployed_at)) {
                $attempts[$period->bucketIndex($deployment->deployed_at)][$key] = $deployment;
            }
        }

        return array_map(fn (int $index): DeliveryWindow => new DeliveryWindow(
            releases: collect($releases[$index]),
            attempts: collect($attempts[$index]),
            hasSignal: $this->deployments->isNotEmpty(),
            days: $days[$index],
            time: $this->time,
        ), array_keys($days));
    }

    /**
     * The most recent deployments with what they shipped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 8): array
    {
        return $this->releases
            ->sortByDesc(fn (array $release): int => $release['deployment']->deployed_at->getTimestamp())
            ->take($limit)
            ->map(fn (array $release): array => [
                'name' => $release['deployment']->name ?? substr((string) $release['deployment']->sha, 0, 7),
                'ref' => $release['deployment']->ref,
                'url' => $release['deployment']->url,
                'deployedAt' => $release['deployment']->deployed_at->toIso8601String(),
                'shipped' => $release['shipped']->count(),
                'failedChange' => $release['failedChange'],
            ])
            ->values()
            ->all();
    }

    /**
     * Pull requests merged into the default branch that no deployment has shipped yet.
     *
     * @return array{count: int, oldestSeconds: int|null}
     */
    public function pending(CarbonImmutable $now): array
    {
        if ($this->deployments->isEmpty()) {
            return ['count' => 0, 'oldestSeconds' => null];
        }

        $lastDeploy = $this->deployments
            ->filter(fn (Deployment $deployment): bool => $deployment->succeeded() && $this->branchOf($deployment) === $this->defaultBranch)
            ->max(fn (Deployment $deployment): int => $deployment->deployed_at->getTimestamp());

        $waiting = $this->pullRequests->filter(fn (PullRequest $pr): bool => $pr->merged_at !== null
            && $pr->base_ref === $this->defaultBranch
            && ($lastDeploy === null || $pr->merged_at->getTimestamp() > $lastDeploy));

        $oldest = $waiting->min(fn (PullRequest $pr): int => $pr->merged_at?->getTimestamp() ?? PHP_INT_MAX);

        return [
            'count' => $waiting->count(),
            'oldestSeconds' => $oldest === null ? null : $this->time->between(CarbonImmutable::createFromTimestamp($oldest), $now),
        ];
    }

    /**
     * @return Collection<int, array{deployment: Deployment, shipped: Collection<int, PullRequest>, failedChange: bool, restoredAt: CarbonImmutable|null}>
     */
    private function buildReleases(): Collection
    {
        // Oldest merge first, so each deployment ships the next slice instead of rescanning every pull request.
        $merged = $this->pullRequests
            ->filter(fn (PullRequest $pr): bool => $pr->merged_at !== null)
            ->sortBy(fn (PullRequest $pr): int => $pr->merged_at->getTimestamp());

        return $this->deployments
            ->filter->succeeded()
            ->sortBy(fn (Deployment $deployment): int => $deployment->deployed_at->getTimestamp())
            ->groupBy(fn (Deployment $deployment): string => (string) $this->branchOf($deployment))
            ->flatMap(function (Collection $deployments, string $branch) use ($merged): array {
                $candidates = $branch === '' ? $merged : $merged->filter(fn (PullRequest $pr): bool => $pr->base_ref === $branch);
                $candidates = $candidates->all();
                $keys = array_keys($candidates);
                $cursor = 0;
                $releases = [];

                foreach ($deployments->values() as $deployment) {
                    // Pull requests merged while the deployment was rolling out belong to the next one.
                    $cutOff = $deployment->cutOffAt()->getTimestamp();
                    $shipped = [];

                    // Everything merged up to the previous deployment was already taken by it.
                    while ($cursor < count($keys) && $candidates[$keys[$cursor]]->merged_at->getTimestamp() <= $cutOff) {
                        $shipped[$keys[$cursor]] = $candidates[$keys[$cursor]];
                        $cursor++;
                    }

                    ksort($shipped);

                    $releases[] = ['deployment' => $deployment, 'shipped' => collect($shipped)->values(), 'failedChange' => false, 'restoredAt' => null];
                }

                // The first deployment's window is open-ended, so it is only a boundary, not a data point.
                array_shift($releases);

                foreach ($releases as $index => $release) {
                    $next = $releases[$index + 1] ?? null;

                    if ($next !== null && $next['shipped']->contains(fn (PullRequest $pr): bool => $pr->isRemediation())) {
                        $releases[$index]['failedChange'] = true;
                        $releases[$index]['restoredAt'] = $next['deployment']->deployed_at;
                    }
                }

                return $releases;
            })
            ->values();
    }

    /**
     * The branch a deployment shipped. Refs that are not a known target branch (tags, SHAs) count as the default branch.
     */
    private function branchOf(Deployment $deployment): ?string
    {
        return $deployment->ref !== null && isset($this->knownBranches[$deployment->ref])
            ? $deployment->ref
            : $this->defaultBranch;
    }
}
