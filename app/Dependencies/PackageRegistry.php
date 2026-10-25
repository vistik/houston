<?php

namespace App\Dependencies;

use App\Enums\DependencyEcosystem;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Looks up the latest stable release of packages on Packagist and the npm registry.
 *
 * Answers are cached for a while and shared by every repository, so watching many repositories that
 * use the same packages asks the registries about each package only once.
 */
class PackageRegistry
{
    private const int CacheHours = 6;

    private const int Concurrency = 10;

    /**
     * @param  list<string>  $names
     * @return array<string, string|null> The latest version by package name, null when it is not known.
     */
    public function latestVersions(DependencyEcosystem $ecosystem, array $names): array
    {
        $names = array_values(array_unique($names));
        $cached = Cache::many(array_map(fn (string $name): string => $this->cacheKey($ecosystem, $name), $names));
        $latest = [];
        $missing = [];

        foreach ($names as $name) {
            $hit = $cached[$this->cacheKey($ecosystem, $name)] ?? null;

            if (is_array($hit)) {
                $latest[$name] = $hit['version'];
            } else {
                $missing[] = $name;
            }
        }

        if ($missing === []) {
            return $latest;
        }

        $responses = Http::pool(
            fn (Pool $pool): array => array_map(
                fn (string $name) => $pool->acceptJson()->timeout(20)->get($this->url($ecosystem, $name)),
                $missing,
            ),
            concurrency: self::Concurrency,
        );

        $fresh = [];

        foreach ($missing as $index => $name) {
            $response = $responses[$index] ?? null;

            // Connection failures and server errors say nothing about the package, so they are asked again next time.
            if (! $response instanceof Response || (! $response->successful() && $response->status() !== 404)) {
                $latest[$name] = null;

                continue;
            }

            $latest[$name] = $response->successful() ? $this->latestIn($ecosystem, $name, $response) : null;
            $fresh[$this->cacheKey($ecosystem, $name)] = ['version' => $latest[$name]];
        }

        if ($fresh !== []) {
            Cache::putMany($fresh, now()->addHours(self::CacheHours));
        }

        return $latest;
    }

    private function url(DependencyEcosystem $ecosystem, string $name): string
    {
        return match ($ecosystem) {
            DependencyEcosystem::Composer => "https://repo.packagist.org/p2/{$name}.json",
            // The `latest` dist-tag is what `npm install` picks; scoped names keep their slash encoded.
            DependencyEcosystem::Npm => 'https://registry.npmjs.org/'.str_replace('/', '%2F', $name).'/latest',
        };
    }

    private function latestIn(DependencyEcosystem $ecosystem, string $name, Response $response): ?string
    {
        if ($ecosystem === DependencyEcosystem::Npm) {
            $version = $response->json('version');

            return is_string($version) ? $version : null;
        }

        // Packagist lists tagged releases only; pre-releases carry a suffix in their normalized version.
        $releases = $response->json("packages.{$name}");

        if (! is_array($releases)) {
            return null;
        }

        $latest = null;

        foreach ($releases as $release) {
            $normalized = $release['version_normalized'] ?? null;

            if (! is_string($normalized) || str_contains($normalized, '-') || ! is_string($release['version'] ?? null)) {
                continue;
            }

            if ($latest === null || version_compare($normalized, $latest['normalized'], '>')) {
                $latest = ['normalized' => $normalized, 'version' => $release['version']];
            }
        }

        return $latest['version'] ?? null;
    }

    private function cacheKey(DependencyEcosystem $ecosystem, string $name): string
    {
        return "houston:registry:{$ecosystem->value}:{$name}";
    }
}
