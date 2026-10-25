<?php

use App\Enums\DeploymentSource;
use App\GitHub\DeploymentSynchronizer;
use App\Models\Deployment;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
    $this->state = $this->repository->syncState();
});

function syncDeployments(Repository $repository): void
{
    app(DeploymentSynchronizer::class)->sync($repository, $repository->syncState(), CarbonImmutable::now(), CarbonImmutable::now()->subDays(180));
}

/**
 * @param  list<array<string, mixed>>  $nodes
 */
function fakeDeployments(array $nodes, array $releases = []): void
{
    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => ['deployments' => [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'nodes' => $nodes,
        ]]]]),
        'api.github.com/repos/acme/app/releases*' => Http::response($releases),
    ]);
}

it('uses GitHub deployments to production environments when the repository has them', function () {
    fakeDeployments([
        ['databaseId' => 3, 'environment' => 'Production', 'commitOid' => 'c3', 'commit' => ['committedDate' => '2026-10-06T09:30:00Z'], 'createdAt' => '2026-10-06T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'SUCCESS', 'createdAt' => '2026-10-06T10:04:00Z']],
        ['databaseId' => 2, 'environment' => 'staging', 'commitOid' => 'c2', 'createdAt' => '2026-10-05T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'SUCCESS', 'createdAt' => '2026-10-05T10:04:00Z']],
        ['databaseId' => 1, 'environment' => 'production', 'commitOid' => 'c1', 'createdAt' => '2026-10-04T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'FAILURE', 'createdAt' => '2026-10-04T10:02:00Z']],
    ]);

    syncDeployments($this->repository);

    expect(Deployment::orderBy('deployed_at')->get(['external_id', 'state', 'sha'])->toArray())->toBe([
        ['external_id' => '1', 'state' => 'failure', 'sha' => 'c1'],
        ['external_id' => '3', 'state' => 'success', 'sha' => 'c3'],
    ])
        ->and(Deployment::firstWhere('external_id', '3')->deployed_at->toIso8601ZuluString())->toBe('2026-10-06T10:04:00Z')
        ->and(Deployment::firstWhere('external_id', '3')->committed_at->toIso8601ZuluString())->toBe('2026-10-06T09:30:00Z')
        ->and($this->repository->sync->fresh()->deploy_source)->toBe(DeploymentSource::Deployments);
});

it('counts deployments that succeeded before a newer one made them inactive', function () {
    fakeDeployments([
        ['databaseId' => 2, 'environment' => 'production', 'commitOid' => 'c2', 'createdAt' => '2026-10-06T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'INACTIVE', 'createdAt' => '2026-10-06T12:00:00Z'], 'statuses' => ['nodes' => [
            ['state' => 'INACTIVE', 'createdAt' => '2026-10-06T12:00:00Z'],
            ['state' => 'SUCCESS', 'createdAt' => '2026-10-06T10:05:00Z'],
            ['state' => 'IN_PROGRESS', 'createdAt' => '2026-10-06T10:01:00Z'],
        ]]],
        ['databaseId' => 1, 'environment' => 'production', 'commitOid' => 'c1', 'createdAt' => '2026-10-05T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'INACTIVE', 'createdAt' => '2026-10-05T12:00:00Z'], 'statuses' => ['nodes' => [
            ['state' => 'INACTIVE', 'createdAt' => '2026-10-05T12:00:00Z'],
        ]]],
    ]);

    syncDeployments($this->repository);

    expect(Deployment::orderBy('external_id')->get(['external_id', 'state'])->toArray())->toBe([
        ['external_id' => '1', 'state' => 'success'],
        ['external_id' => '2', 'state' => 'success'],
    ])
        ->and(Deployment::firstWhere('external_id', '2')->deployed_at->toIso8601ZuluString())->toBe('2026-10-06T10:05:00Z')
        ->and(Deployment::firstWhere('external_id', '1')->deployed_at->toIso8601ZuluString())->toBe('2026-10-05T10:00:00Z');
});

it('links deployments of a tag to the tag and others to their commit', function () {
    fakeDeployments([
        ['databaseId' => 2, 'environment' => 'production', 'commitOid' => 'c2', 'createdAt' => '2026-10-06T10:00:00Z', 'ref' => ['name' => 'v1.2.0', 'prefix' => 'refs/tags/'], 'latestStatus' => ['state' => 'SUCCESS', 'createdAt' => '2026-10-06T10:04:00Z']],
        ['databaseId' => 1, 'environment' => 'production', 'commitOid' => 'c1', 'createdAt' => '2026-10-05T10:00:00Z', 'ref' => ['name' => 'main', 'prefix' => 'refs/heads/'], 'latestStatus' => ['state' => 'SUCCESS', 'createdAt' => '2026-10-05T10:04:00Z']],
    ]);

    syncDeployments($this->repository);

    expect(Deployment::orderBy('external_id')->pluck('url')->all())->toBe([
        'https://github.com/acme/app/commit/c1',
        'https://github.com/acme/app/releases/tag/v1.2.0',
    ]);
});

it('falls back to published releases', function () {
    fakeDeployments([], [
        ['id' => 11, 'tag_name' => 'v2.1.0', 'draft' => false, 'prerelease' => false, 'target_commitish' => '2.x', 'published_at' => '2026-10-06T08:00:00Z', 'created_at' => '2026-10-06T07:00:00Z', 'html_url' => 'https://github.com/acme/app/releases/v2.1.0'],
        ['id' => 10, 'tag_name' => 'v2.1.0-beta', 'draft' => false, 'prerelease' => true, 'target_commitish' => '2.x', 'published_at' => '2026-10-05T08:00:00Z', 'created_at' => '2026-10-05T08:00:00Z'],
        ['id' => 9, 'tag_name' => 'v2.2.0', 'draft' => true, 'prerelease' => false, 'target_commitish' => '2.x', 'published_at' => null, 'created_at' => '2026-10-07T08:00:00Z'],
    ]);

    syncDeployments($this->repository);

    expect(Deployment::sole())
        ->name->toBe('v2.1.0')
        ->ref->toBe('2.x')
        ->source->toBe(DeploymentSource::Releases)
        ->and(Deployment::sole()->committed_at->toIso8601ZuluString())->toBe('2026-10-06T07:00:00Z')
        ->and($this->repository->sync->fresh()->deploy_source)->toBe(DeploymentSource::Releases);
});

it('switches to deployments once a repository starts recording them', function () {
    Deployment::factory()->for($this->repository)->create(['source' => DeploymentSource::Releases]);

    fakeDeployments([
        ['databaseId' => 5, 'environment' => 'production', 'commitOid' => 'c5', 'createdAt' => '2026-10-06T10:00:00Z', 'ref' => ['name' => 'main'], 'latestStatus' => ['state' => 'SUCCESS', 'createdAt' => '2026-10-06T10:01:00Z']],
    ]);

    syncDeployments($this->repository);

    expect(Deployment::pluck('source')->all())->toBe([DeploymentSource::Deployments]);
});

it('records that a repository has no deploy signal', function () {
    fakeDeployments([]);

    syncDeployments($this->repository);

    expect($this->repository->sync->fresh()->deploy_source)->toBeNull();
});
