<?php

namespace App\GitHub;

use App\Dependencies\PackageRegistry;
use App\Enums\DependencyDrift;
use App\Enums\DependencyEcosystem;
use App\Models\Dependency;
use App\Models\DependencySnapshot;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks how far behind the direct dependencies of a repository are.
 *
 * Reads the Composer and npm manifests and lock files at the root of the default branch, and compares
 * the installed versions with the latest releases on Packagist and the npm registry. Without a lock file,
 * as in most libraries, it checks whether the version constraint lets in the latest release instead.
 * Only direct dependencies count: those are the ones a team chooses to upgrade.
 */
class DependencySynchronizer
{
    /**
     * Fetches all four files in one query. Blobs over GitHub's size limit come back truncated.
     */
    private const string ManifestsQuery = <<<'GRAPHQL'
        query($owner: String!, $name: String!) {
          repository(owner: $owner, name: $name) {
            composerJson: object(expression: "HEAD:composer.json") { ...File }
            composerLock: object(expression: "HEAD:composer.lock") { ...File }
            packageJson: object(expression: "HEAD:package.json") { ...File }
            packageLock: object(expression: "HEAD:package-lock.json") { ...File }
          }
        }
        fragment File on Blob { oid text isTruncated }
        GRAPHQL;

    /**
     * The installed versions of a lock file only change with its contents, so large ones are read once.
     */
    private const int LockFileCacheDays = 7;

    public function __construct(private GitHubClient $github, private PackageRegistry $registry) {}

    public function sync(Repository $repository): void
    {
        $files = $this->github->graphql(self::ManifestsQuery, [
            'owner' => $repository->owner,
            'name' => $repository->name,
        ])['repository'] ?? [];

        $rows = [
            ...$this->composer($repository, $files),
            ...$this->npm($repository, $files),
        ];

        $checkedAt = CarbonImmutable::now();

        DB::transaction(function () use ($repository, $rows, $checkedAt): void {
            $repository->dependencies()->delete();

            if ($rows !== []) {
                Dependency::insert(array_map(fn (array $row): array => [...$row, 'repository_id' => $repository->id], $rows));
            }

            $drifts = array_map(fn (array $row): DependencyDrift => DependencyDrift::from($row['drift']), $rows);

            DependencySnapshot::upsert([[
                'repository_id' => $repository->id,
                'recorded_on' => $checkedAt->toDateString(),
                'total' => count($rows),
                'outdated' => count(array_filter($drifts, fn (DependencyDrift $drift): bool => $drift->isOutdated())),
                'major' => count(array_filter($drifts, fn (DependencyDrift $drift): bool => $drift === DependencyDrift::Major)),
                'checked_at' => $checkedAt,
            ]], ['repository_id', 'recorded_on'], ['total', 'outdated', 'major', 'checked_at']);
        });
    }

    /**
     * @param  array<string, mixed>  $files
     * @return list<array<string, mixed>>
     */
    private function composer(Repository $repository, array $files): array
    {
        $manifest = $this->decode($repository, 'composer.json', $files['composerJson'] ?? null);

        if ($manifest === null) {
            return [];
        }

        $installed = $this->lockedVersions($repository, 'composer.lock', $files['composerLock'] ?? null, function (array $lock): array {
            $versions = [];

            foreach ([...$lock['packages'] ?? [], ...$lock['packages-dev'] ?? []] as $package) {
                if (is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                    $versions[strtolower($package['name'])] = $package['version'];
                }
            }

            return $versions;
        });

        // Platform requirements such as `php` and `ext-json` have no slash and are not packages.
        $declared = $this->declared($manifest, ['require' => false, 'require-dev' => true], fn (string $name): bool => str_contains($name, '/'));

        return $this->rows(DependencyEcosystem::Composer, array_change_key_case($declared), $installed);
    }

    /**
     * @param  array<string, mixed>  $files
     * @return list<array<string, mixed>>
     */
    private function npm(Repository $repository, array $files): array
    {
        $manifest = $this->decode($repository, 'package.json', $files['packageJson'] ?? null);

        if ($manifest === null) {
            return [];
        }

        $installed = $this->lockedVersions($repository, 'package-lock.json', $files['packageLock'] ?? null, function (array $lock): array {
            $versions = [];

            // Lock files from npm 7 on list packages by their path; older ones by name.
            foreach ($lock['packages'] ?? [] as $path => $package) {
                if (is_string($path) && str_starts_with($path, 'node_modules/') && ! str_contains(substr($path, 13), '/node_modules/') && is_string($package['version'] ?? null)) {
                    $versions[substr($path, 13)] = $package['version'];
                }
            }

            foreach ($lock['dependencies'] ?? [] as $name => $package) {
                if (is_string($name) && is_string($package['version'] ?? null)) {
                    $versions[$name] ??= $package['version'];
                }
            }

            return $versions;
        });

        $declared = $this->declared($manifest, ['dependencies' => false, 'devDependencies' => true]);

        return $this->rows(DependencyEcosystem::Npm, $declared, $installed);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, bool>  $sections  Whether each section holds development dependencies.
     * @param  (callable(string): bool)|null  $isPackage
     * @return array<string, array{constraint: string, dev: bool}>
     */
    private function declared(array $manifest, array $sections, ?callable $isPackage = null): array
    {
        $declared = [];

        foreach ($sections as $section => $dev) {
            foreach (is_array($manifest[$section] ?? null) ? $manifest[$section] : [] as $name => $constraint) {
                if (is_string($name) && is_string($constraint) && ($isPackage === null || $isPackage($name))) {
                    $declared[$name] ??= ['constraint' => $constraint, 'dev' => $dev];
                }
            }
        }

        return $declared;
    }

    /**
     * @param  array<string, array{constraint: string, dev: bool}>  $declared
     * @param  array<string, string>  $installed
     * @return list<array<string, mixed>>
     */
    private function rows(DependencyEcosystem $ecosystem, array $declared, array $installed): array
    {
        // Dependencies from git, a path or a workspace aren't on the registry.
        $published = array_keys(array_filter($declared, fn (array $dependency): bool => ! preg_match('~^(file|link|workspace|git|github|http|https|npm|portal):|\.git$~', $dependency['constraint'])));
        $latest = $published === [] ? [] : $this->registry->latestVersions($ecosystem, array_map('strval', $published));
        $rows = [];

        foreach ($declared as $name => $dependency) {
            $name = (string) $name;
            $version = $installed[$name] ?? null;
            $latestVersion = $latest[$name] ?? null;

            $rows[] = [
                'ecosystem' => $ecosystem->value,
                'name' => $name,
                'constraint' => str($dependency['constraint'])->limit(250)->toString(),
                'version' => $version,
                'latest_version' => $latestVersion,
                'is_dev' => $dependency['dev'],
                'drift' => ($version === null
                    ? DependencyDrift::allowedBy($dependency['constraint'], $latestVersion)
                    : DependencyDrift::between($version, $latestVersion))->value,
            ];
        }

        return $rows;
    }

    /**
     * @param  callable(array<string, mixed>): array<string, string>  $versions
     * @return array<string, string>
     */
    private function lockedVersions(Repository $repository, string $path, mixed $blob, callable $versions): array
    {
        if (! is_array($blob) || ! is_string($blob['oid'] ?? null)) {
            return [];
        }

        if (! ($blob['isTruncated'] ?? false)) {
            return $versions($this->decode($repository, $path, $blob) ?? []);
        }

        return Cache::remember("houston:lockfile:{$blob['oid']}", now()->addDays(self::LockFileCacheDays), fn (): array => $versions($this->decode($repository, $path, $blob) ?? []));
    }

    /**
     * Read a JSON file from its blob, fetching it separately when GitHub truncated it.
     *
     * @return array<string, mixed>|null
     */
    private function decode(Repository $repository, string $path, mixed $blob): ?array
    {
        if (! is_array($blob)) {
            return null;
        }

        $text = ($blob['isTruncated'] ?? false) ? $this->github->rawFile($repository->fullName(), $path) : ($blob['text'] ?? null);
        $json = is_string($text) ? json_decode($text, true) : null;

        return is_array($json) ? $json : null;
    }
}
