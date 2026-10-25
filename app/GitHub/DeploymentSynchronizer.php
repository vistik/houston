<?php

namespace App\GitHub;

use App\Enums\DeploymentSource;
use App\Enums\DeploymentState;
use App\Models\Deployment;
use App\Models\Repository;
use App\Models\RepositorySync;
use Carbon\CarbonImmutable;

/**
 * Caches production deployments, detecting per repository where they come from.
 *
 * Repositories that record GitHub Deployments to a production-like environment use those.
 * Everything else falls back to published releases, which is how libraries ship.
 */
class DeploymentSynchronizer
{
    private const string DeploymentsQuery = <<<'GRAPHQL'
        query($owner: String!, $name: String!, $cursor: String) {
          repository(owner: $owner, name: $name) {
            deployments(first: 50, after: $cursor, orderBy: {field: CREATED_AT, direction: DESC}) {
              pageInfo { hasNextPage endCursor }
              nodes {
                databaseId environment commitOid createdAt
                commit { committedDate }
                ref { name prefix }
                latestStatus { state createdAt }
                statuses(first: 10) { nodes { state createdAt } }
              }
            }
          }
        }
        GRAPHQL;

    private const int MaxReleasePages = 10;

    public function __construct(private GitHubClient $github) {}

    /**
     * @param  bool  $backfill  Fetch everything since the lookback start, e.g. after the lookback was extended.
     */
    public function sync(Repository $repository, RepositorySync $state, CarbonImmutable $startedAt, CarbonImmutable $lookbackStart, bool $backfill = false): void
    {
        // Statuses of recent deployments can still change, so overlap the incremental window.
        $since = $backfill ? $lookbackStart : ($state->deployments_synced_at?->subDays(2) ?? $lookbackStart);

        $deployments = $this->fetchDeployments($repository, $since);
        $alreadyUsesDeployments = $repository->deployments()->where('source', DeploymentSource::Deployments)->exists();

        $source = $deployments !== [] || $alreadyUsesDeployments
            ? DeploymentSource::Deployments
            : DeploymentSource::Releases;

        $rows = $source === DeploymentSource::Deployments ? $deployments : $this->fetchReleases($repository, $since);

        $repository->deployments()->where('source', '!=', $source)->delete();

        if ($rows !== []) {
            Deployment::upsert($rows, ['repository_id', 'source', 'external_id']);
        }

        $hasAny = $repository->deployments()->exists();

        $state->update([
            'deploy_source' => $hasAny ? $source : null,
            'deployments_synced_at' => $startedAt,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchDeployments(Repository $repository, CarbonImmutable $since): array
    {
        $pattern = config()->string('houston.deploy_environment_pattern');
        $rows = [];
        $cursor = null;

        do {
            $connection = $this->github->graphql(self::DeploymentsQuery, [
                'owner' => $repository->owner,
                'name' => $repository->name,
                'cursor' => $cursor,
            ])['repository']['deployments'] ?? null;

            if (! is_array($connection)) {
                break;
            }

            foreach ($connection['nodes'] as $node) {
                $createdAt = CarbonImmutable::parse($node['createdAt']);

                if ($createdAt->lt($since)) {
                    return $rows;
                }

                if (preg_match($pattern, (string) ($node['environment'] ?? '')) !== 1) {
                    continue;
                }

                // GitHub only marks deployments inactive that succeeded, once a newer one succeeds. It prunes
                // older statuses, so the success itself is timed only while it is still in the history.
                $statuses = data_get($node, 'statuses.nodes');
                $succeededAt = is_array($statuses)
                    ? collect($statuses)->first(fn (mixed $status): bool => DeploymentState::fromGitHub(data_get($status, 'state')) === DeploymentState::Success)['createdAt'] ?? null
                    : null;
                $latest = DeploymentState::fromGitHub(data_get($node, 'latestStatus.state'));
                $succeededAt ??= $latest === DeploymentState::Success ? data_get($node, 'latestStatus.createdAt') : null;
                $state = $succeededAt !== null || $latest === DeploymentState::Inactive ? DeploymentState::Success : $latest;
                $committedAt = data_get($node, 'commit.committedDate');

                $rows[] = [
                    'repository_id' => $repository->id,
                    'source' => DeploymentSource::Deployments->value,
                    'external_id' => (string) $node['databaseId'],
                    'name' => null,
                    'environment' => $node['environment'],
                    'ref' => data_get($node, 'ref.name'),
                    'sha' => $node['commitOid'] ?? null,
                    'state' => $state->value,
                    'url' => $this->deploymentUrl($repository, $node),
                    'committed_at' => $committedAt !== null ? CarbonImmutable::parse($committedAt) : null,
                    'deployed_at' => $succeededAt !== null ? CarbonImmutable::parse($succeededAt) : $createdAt,
                ];
            }

            $cursor = $connection['pageInfo']['endCursor'] ?? null;
        } while ($connection['pageInfo']['hasNextPage'] ?? false);

        return $rows;
    }

    /**
     * Links a deployment to the tag it shipped, or to its commit when it shipped a branch.
     *
     * @param  array<string, mixed>  $node
     */
    private function deploymentUrl(Repository $repository, array $node): ?string
    {
        if (data_get($node, 'ref.prefix') === 'refs/tags/') {
            return "https://github.com/{$repository->fullName()}/releases/tag/".data_get($node, 'ref.name');
        }

        return isset($node['commitOid']) ? "https://github.com/{$repository->fullName()}/commit/{$node['commitOid']}" : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchReleases(Repository $repository, CarbonImmutable $since): array
    {
        $rows = [];

        for ($page = 1; $page <= self::MaxReleasePages; $page++) {
            $releases = $this->github->get("repos/{$repository->owner}/{$repository->name}/releases", ['per_page' => 100, 'page' => $page]);

            foreach ($releases as $release) {
                if (! is_array($release) || $release['draft'] || $release['prerelease'] || empty($release['published_at'])) {
                    continue;
                }

                $publishedAt = CarbonImmutable::parse($release['published_at']);

                if ($publishedAt->lt($since)) {
                    continue;
                }

                $rows[] = [
                    'repository_id' => $repository->id,
                    'source' => DeploymentSource::Releases->value,
                    'external_id' => (string) $release['id'],
                    'name' => $release['tag_name'],
                    'environment' => null,
                    'ref' => $release['target_commitish'] ?? null,
                    'sha' => null,
                    'state' => DeploymentState::Success->value,
                    'url' => $release['html_url'] ?? null,
                    // GitHub dates a release by the commit it was cut from.
                    'committed_at' => empty($release['created_at']) ? null : CarbonImmutable::parse($release['created_at']),
                    'deployed_at' => $publishedAt,
                ];
            }

            $oldest = collect($releases)->filter(fn ($release): bool => is_array($release) && ! empty($release['created_at']))->min('created_at');

            if (count($releases) < 100 || ($oldest !== null && CarbonImmutable::parse($oldest)->lt($since))) {
                break;
            }
        }

        return $rows;
    }
}
