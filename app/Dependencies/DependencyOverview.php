<?php

namespace App\Dependencies;

use App\Enums\DependencyDrift;
use App\Models\Dependency;
use App\Models\DependencySnapshot;
use App\Models\Repository;

/**
 * How far behind a repository's direct dependencies are, for its page.
 */
class DependencyOverview
{
    /**
     * How long ago the snapshot lies that the current drift is compared with.
     */
    private const int CompareWithDaysAgo = 30;

    /**
     * @return array{
     *     checkedAt: string,
     *     total: int,
     *     outdated: int,
     *     major: int,
     *     minor: int,
     *     patch: int,
     *     unknown: int,
     *     byConstraint: int,
     *     earlier: array{recordedOn: string, outdated: int, major: int}|null,
     *     packages: list<array{ecosystem: string, name: string, version: string|null, constraint: string, latestVersion: string|null, drift: string, dev: bool}>,
     * }|null Null until the dependencies were first checked. Packages lists every dependency, the furthest behind first.
     */
    public function for(Repository $repository): ?array
    {
        $latest = $repository->dependencySnapshots()->latest('recorded_on')->first();

        if ($latest === null) {
            return null;
        }

        $earlier = $repository->dependencySnapshots()
            ->where('recorded_on', '<=', $latest->recorded_on->subDays(self::CompareWithDaysAgo)->toDateString())
            ->latest('recorded_on')
            ->first();

        $dependencies = $repository->dependencies()->get();
        $counts = $dependencies->countBy(fn (Dependency $dependency): string => $dependency->drift->value);
        $severity = array_flip(array_map(fn (DependencyDrift $drift): string => $drift->value, [DependencyDrift::Major, DependencyDrift::Minor, DependencyDrift::Patch, DependencyDrift::Unknown, DependencyDrift::Current]));

        return [
            'checkedAt' => $latest->checked_at->toIso8601String(),
            'total' => $dependencies->count(),
            'outdated' => $dependencies->filter(fn (Dependency $dependency): bool => $dependency->drift->isOutdated())->count(),
            'major' => $counts[DependencyDrift::Major->value] ?? 0,
            'minor' => $counts[DependencyDrift::Minor->value] ?? 0,
            'patch' => $counts[DependencyDrift::Patch->value] ?? 0,
            'unknown' => $counts[DependencyDrift::Unknown->value] ?? 0,
            // Without a lock file saying what is installed, the constraint was compared instead.
            'byConstraint' => $dependencies->filter(fn (Dependency $dependency): bool => $dependency->version === null && $dependency->drift !== DependencyDrift::Unknown)->count(),
            'earlier' => $earlier instanceof DependencySnapshot ? [
                'recordedOn' => $earlier->recorded_on->toDateString(),
                'outdated' => $earlier->outdated,
                'major' => $earlier->major,
            ] : null,
            'packages' => array_values($dependencies
                ->sortBy([
                    fn (Dependency $a, Dependency $b): int => $severity[$a->drift->value] <=> $severity[$b->drift->value],
                    fn (Dependency $a, Dependency $b): int => [$a->is_dev, $a->name] <=> [$b->is_dev, $b->name],
                ])
                ->map(fn (Dependency $dependency): array => [
                    'ecosystem' => $dependency->ecosystem->value,
                    'name' => $dependency->name,
                    'version' => $dependency->version,
                    'constraint' => $dependency->constraint,
                    'latestVersion' => $dependency->latest_version,
                    'drift' => $dependency->drift->value,
                    'dev' => $dependency->is_dev,
                ])
                ->all()),
        ];
    }
}
