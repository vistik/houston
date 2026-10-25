<?php

use App\Enums\DependencyDrift;
use App\GitHub\DependencySynchronizer;
use App\Jobs\CheckDependencies;
use App\Models\Dependency;
use App\Models\DependencySnapshot;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
});

/**
 * A blob as the manifests query returns it.
 *
 * @param  array<string, mixed>  $json
 * @return array<string, mixed>
 */
function blob(array $json, bool $truncated = false): array
{
    return ['oid' => md5(json_encode($json)), 'text' => $truncated ? null : json_encode($json), 'isTruncated' => $truncated];
}

/**
 * @param  array<string, array<string, mixed>|null>  $files  Blobs by their alias in the manifests query.
 */
function fakeManifests(array $files): void
{
    Http::fake(['api.github.com/graphql' => Http::response(['data' => ['repository' => [
        'composerJson' => null,
        'composerLock' => null,
        'packageJson' => null,
        'packageLock' => null,
        ...$files,
    ]]])]);
}

/**
 * Packagist's answer for a package, newest release first like Packagist sorts them.
 *
 * @param  list<string>  $versions  Normalized versions, e.g. `13.2.0.0` or `14.0.0.0-beta1`.
 */
function packagist(string $name, array $versions): array
{
    return ['minified' => 'composer/2.0', 'packages' => [$name => array_map(
        fn (string $normalized): array => ['version' => 'v'.preg_replace('/\.0(-|$)/', '$1', $normalized, 1), 'version_normalized' => $normalized],
        $versions,
    )]];
}

function checkDependencies(Repository $repository): void
{
    app(DependencySynchronizer::class)->sync($repository);
}

it('compares direct dependencies with the latest stable releases', function () {
    fakeManifests([
        'composerJson' => blob([
            'require' => ['php' => '^8.4', 'ext-json' => '*', 'laravel/framework' => '^12.0', 'acme/tiny' => '^0.3', 'acme/private' => '^1.0'],
            'require-dev' => ['pestphp/pest' => '^4.0', 'acme/edge' => 'dev-main'],
        ]),
        'composerLock' => blob([
            'packages' => [
                ['name' => 'laravel/framework', 'version' => 'v12.4.1'],
                ['name' => 'acme/tiny', 'version' => '0.3.2'],
                ['name' => 'acme/private', 'version' => '1.0.0'],
                ['name' => 'symfony/console', 'version' => 'v7.0.0'],
            ],
            'packages-dev' => [
                ['name' => 'pestphp/pest', 'version' => 'v4.1.0'],
                ['name' => 'acme/edge', 'version' => 'dev-main'],
            ],
        ]),
        'packageJson' => blob([
            'dependencies' => ['react' => '^19.0.0', '@inertiajs/react' => '^3.0.0', 'local-lib' => 'file:../lib'],
            'devDependencies' => ['typescript' => '^5.8.0', 'vite' => '^7.0.0'],
        ]),
        'packageLock' => blob(['lockfileVersion' => 3, 'packages' => [
            '' => ['name' => 'app'],
            'node_modules/react' => ['version' => '19.1.0'],
            'node_modules/@inertiajs/react' => ['version' => '3.0.0'],
            'node_modules/typescript' => ['version' => '5.8.3'],
            'node_modules/vite' => ['version' => '7.1.0'],
            'node_modules/vite/node_modules/esbuild' => ['version' => '0.25.0'],
        ]]),
    ]);

    Http::fake([
        'repo.packagist.org/p2/laravel/framework.json' => Http::response(packagist('laravel/framework', ['14.0.0.0-beta1', '13.2.0.0', '12.4.1.0'])),
        'repo.packagist.org/p2/acme/tiny.json' => Http::response(packagist('acme/tiny', ['0.4.0.0', '0.3.2.0'])),
        'repo.packagist.org/p2/acme/private.json' => Http::response(['status' => 'error'], 404),
        'repo.packagist.org/p2/pestphp/pest.json' => Http::response(packagist('pestphp/pest', ['4.1.2.0', '4.1.0.0'])),
        'repo.packagist.org/p2/acme/edge.json' => Http::response(packagist('acme/edge', ['2.0.0.0'])),
        'registry.npmjs.org/react/latest' => Http::response(['version' => '19.2.0']),
        'registry.npmjs.org/@inertiajs%2Freact/latest' => Http::response(['version' => '3.0.0']),
        'registry.npmjs.org/typescript/latest' => Http::response(['version' => '6.0.1']),
        'registry.npmjs.org/vite/latest' => Http::response(['version' => '7.1.0']),
    ]);

    checkDependencies($this->repository);

    expect(Dependency::orderBy('ecosystem')->orderBy('name')->get()->map(fn (Dependency $dependency): array => [
        $dependency->name, $dependency->version, $dependency->latest_version, $dependency->drift, $dependency->is_dev,
    ])->all())->toBe([
        ['acme/edge', 'dev-main', 'v2.0.0', DependencyDrift::Unknown, true],
        ['acme/private', '1.0.0', null, DependencyDrift::Unknown, false],
        ['acme/tiny', '0.3.2', 'v0.4.0', DependencyDrift::Minor, false],
        ['laravel/framework', 'v12.4.1', 'v13.2.0', DependencyDrift::Major, false],
        ['pestphp/pest', 'v4.1.0', 'v4.1.2', DependencyDrift::Patch, true],
        ['@inertiajs/react', '3.0.0', '3.0.0', DependencyDrift::Current, false],
        ['local-lib', null, null, DependencyDrift::Unknown, false],
        ['react', '19.1.0', '19.2.0', DependencyDrift::Minor, false],
        ['typescript', '5.8.3', '6.0.1', DependencyDrift::Major, true],
        ['vite', '7.1.0', '7.1.0', DependencyDrift::Current, true],
    ])
        ->and(DependencySnapshot::sole()->only(['total', 'outdated', 'major']))->toBe(['total' => 10, 'outdated' => 5, 'major' => 2]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'local-lib'));
});

it('checks the version constraints of a repository without a lock file', function () {
    fakeManifests([
        'composerJson' => blob(['require' => ['brick/math' => '^0.14 || ^1.0', 'symfony/console' => '^6.4']]),
    ]);

    Http::fake([
        'repo.packagist.org/p2/brick/math.json' => Http::response(packagist('brick/math', ['1.0.0.0', '0.14.0.0'])),
        'repo.packagist.org/p2/symfony/console.json' => Http::response(packagist('symfony/console', ['7.3.0.0', '6.4.0.0'])),
    ]);

    checkDependencies($this->repository);

    expect(Dependency::orderBy('name')->get()->map(fn (Dependency $dependency): array => [
        $dependency->name, $dependency->version, $dependency->latest_version, $dependency->drift,
    ])->all())->toBe([
        ['brick/math', null, 'v1.0.0', DependencyDrift::Current],
        ['symfony/console', null, 'v7.3.0', DependencyDrift::Major],
    ]);
});

it('fetches lock files too large for GraphQL separately', function () {
    $lock = ['lockfileVersion' => 1, 'dependencies' => ['lodash' => ['version' => '4.17.20']]];

    fakeManifests([
        'packageJson' => blob(['dependencies' => ['lodash' => '^4.17.0']]),
        'packageLock' => blob($lock, truncated: true),
    ]);

    Http::fake([
        'api.github.com/repos/acme/app/contents/package-lock.json' => Http::response(json_encode($lock)),
        'registry.npmjs.org/lodash/latest' => Http::response(['version' => '4.17.21']),
    ]);

    checkDependencies($this->repository);

    expect(Dependency::sole())
        ->version->toBe('4.17.20')
        ->drift->toBe(DependencyDrift::Patch);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'contents/package-lock.json')
        && $request->hasHeader('Accept', 'application/vnd.github.raw+json'));
});

it('asks the registries about a package once for every repository', function () {
    $other = Repository::factory()->create(['owner' => 'acme', 'name' => 'site']);

    fakeManifests([
        'packageJson' => blob(['dependencies' => ['react' => '^19.0.0']]),
        'packageLock' => blob(['lockfileVersion' => 3, 'packages' => ['node_modules/react' => ['version' => '19.1.0']]]),
    ]);

    Http::fake(['registry.npmjs.org/*' => Http::response(['version' => '19.2.0'])]);

    checkDependencies($this->repository);
    checkDependencies($other);

    Http::assertSentCount(3);
    expect(Dependency::pluck('drift')->all())->toBe([DependencyDrift::Minor, DependencyDrift::Minor]);
});

it('asks the registries again after they failed', function () {
    fakeManifests([
        'packageJson' => blob(['dependencies' => ['react' => '^19.0.0']]),
        'packageLock' => blob(['lockfileVersion' => 3, 'packages' => ['node_modules/react' => ['version' => '19.1.0']]]),
    ]);

    Http::fake(['registry.npmjs.org/*' => Http::sequence()
        ->push('Service Unavailable', 503)
        ->push(['version' => '19.2.0'])]);

    checkDependencies($this->repository);
    expect(Dependency::sole()->drift)->toBe(DependencyDrift::Unknown);

    checkDependencies($this->repository);
    expect(Dependency::sole()->drift)->toBe(DependencyDrift::Minor);
});

it('keeps one snapshot a day and forgets dependencies that were removed', function () {
    Dependency::factory()->for($this->repository)->create(['name' => 'acme/removed']);
    fakeManifests([]);

    checkDependencies($this->repository);
    CarbonImmutable::setTestNow('2026-10-07 18:00:00');
    checkDependencies($this->repository);

    expect(Dependency::count())->toBe(0)
        ->and(DependencySnapshot::sole())
        ->total->toBe(0)
        ->checked_at->toIso8601ZuluString()->toBe('2026-10-07T18:00:00Z');
});

it('waits for the next sync when GitHub is rate limited', function () {
    Http::fake(['api.github.com/graphql' => Http::response(
        ['errors' => [['type' => 'RATE_LIMITED', 'message' => 'API rate limit exceeded']]],
        200,
        ['x-ratelimit-reset' => (string) now()->addHour()->getTimestamp()],
    )]);

    CheckDependencies::dispatchSync($this->repository);

    expect(DependencySnapshot::count())->toBe(0);
});
