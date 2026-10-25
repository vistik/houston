<?php

use App\Enums\ChecksState;
use App\Enums\PullRequestState;
use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\Enums\ReviewState;
use App\Enums\WorkflowRunConclusion;
use App\GitHub\GitHubException;
use App\GitHub\RepositorySynchronizer;
use App\Jobs\CheckDependencies;
use App\Jobs\WarmReportCache;
use App\Metrics\Granularity;
use App\Metrics\Period;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RepositorySync;
use App\Models\WorkflowRun;
use App\Reports\ReportCache;
use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pullRequestNode(int $number, array $overrides = []): array
{
    return array_replace_recursive([
        'number' => $number,
        'title' => "PR {$number}",
        'url' => "https://github.com/acme/app/pull/{$number}",
        'state' => 'MERGED',
        'isDraft' => false,
        'createdAt' => '2026-10-01T10:00:00Z',
        'updatedAt' => '2026-10-02T10:00:00Z',
        'mergedAt' => '2026-10-02T10:00:00Z',
        'closedAt' => '2026-10-02T10:00:00Z',
        'additions' => 40,
        'deletions' => 10,
        'changedFiles' => 3,
        'baseRefName' => 'main',
        'headRefName' => 'feature',
        'author' => ['__typename' => 'User', 'login' => 'alice', 'avatarUrl' => 'https://avatars/alice'],
        'comments' => ['totalCount' => 0, 'nodes' => []],
        'commits' => ['totalCount' => 2],
        'firstCommit' => ['nodes' => [['commit' => ['authoredDate' => '2026-10-01T08:00:00Z', 'committedDate' => '2026-10-01T08:00:00Z']]]],
        'lastCommit' => ['nodes' => [['commit' => ['statusCheckRollup' => ['state' => 'SUCCESS']]]]],
        'labels' => ['nodes' => []],
        'reviews' => ['nodes' => []],
        'draftEvents' => ['nodes' => []],
        'activity' => ['nodes' => []],
    ], $overrides);
}

/**
 * The pull requests a search like `updated:2026-10-01T00:00:00Z..2026-10-02T00:00:00Z` matches.
 *
 * @param  list<array<string, mixed>>  $nodes
 * @return list<array<string, mixed>>
 */
function searchPullRequests(array $nodes, string $search): array
{
    preg_match('/updated:(\S+)\.\.(\S+)/', $search, $range);

    return array_values(array_filter($nodes, fn (array $node): bool => CarbonImmutable::parse($node['updatedAt'])
        ->between(CarbonImmutable::parse($range[1]), CarbonImmutable::parse($range[2]))));
}

/**
 * Answer the GraphQL queries of a sync from the given pull requests: counting and paging through
 * searches by update time, listing open pull requests and looking them up by number.
 *
 * @param  list<array<string, mixed>>  $updated
 * @param  list<array<string, mixed>>  $open
 * @param  (Closure(string): (int|null))|null  $count  Overrides the count of a search.
 * @param  (Closure(string): bool)|null  $fails  Whether fetching a search fails.
 */
function fakeGraphql(array $updated, array $open = [], ?Closure $count = null, ?Closure $fails = null): Closure
{
    return function (Request $request) use ($updated, $open, $count, $fails) {
        $query = $request['query'];
        $variables = $request['variables'] ?? [];

        if (str_contains($query, 'defaultBranchRef')) {
            return Http::response(['data' => ['repository' => [
                'description' => 'The app',
                'createdAt' => '2024-03-15T09:00:00Z',
                'isPrivate' => true,
                'defaultBranchRef' => ['name' => 'main'],
                'owner' => ['avatarUrl' => 'https://avatars/acme'],
            ]]]);
        }

        if (str_contains($query, 'issueCount')) {
            return Http::response(['data' => collect($variables)->mapWithKeys(fn (string $search, string $key): array => [
                's'.substr($key, 1) => ['issueCount' => ($count ? $count($search) : null) ?? count(searchPullRequests($updated, $search))],
            ])->all()]);
        }

        if (str_contains($query, 'search(')) {
            if ($fails && $fails($variables['query'])) {
                return Http::response(['errors' => [['message' => 'Something went wrong']]]);
            }

            $offset = (int) ($variables['cursor'] ?? 0);
            $matches = searchPullRequests($updated, $variables['query']);

            return Http::response(['data' => ['search' => [
                'pageInfo' => ['hasNextPage' => count($matches) > $offset + 50, 'endCursor' => (string) ($offset + 50)],
                'nodes' => array_slice($matches, $offset, 50),
            ]]]);
        }

        if (str_contains($query, 'states: OPEN')) {
            return Http::response(['data' => ['repository' => ['pullRequests' => [
                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                'nodes' => array_map(fn (array $node): array => Arr::only($node, ['number', 'updatedAt']), $open),
            ]]]]);
        }

        preg_match_all('/pr(\d+): pullRequest/', $query, $numbers);

        return Http::response(['data' => ['repository' => collect($open)
            ->filter(fn (array $node): bool => in_array((string) $node['number'], $numbers[1], true))
            ->mapWithKeys(fn (array $node): array => ["pr{$node['number']}" => $node])
            ->all()]]);
    };
}

/**
 * @param  list<array<string, mixed>>  $updated
 * @param  list<array<string, mixed>>  $open
 * @param  list<array<string, mixed>>|Closure  $runs
 */
function fakeGitHub(array $updated, array $open = [], array|Closure $runs = []): void
{
    Http::fake([
        'api.github.com/graphql' => fakeGraphql($updated, $open),
        'api.github.com/repos/acme/app/actions/runs*' => function () use ($runs) {
            $runs = value($runs);

            return Http::response(['total_count' => count($runs), 'workflow_runs' => $runs]);
        },
    ]);
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    // Repositories without releases unless a test says otherwise.
    Http::fake(['api.github.com/repos/acme/app/releases*' => Http::response([])]);
});

it('skips connection nodes that GitHub returns as null', function () {
    fakeGitHub([
        pullRequestNode(1, [
            'reviews' => ['nodes' => [
                null,
                ['author' => ['__typename' => 'User', 'login' => 'carol'], 'state' => 'APPROVED', 'submittedAt' => '2026-10-02T09:00:00Z', 'comments' => ['totalCount' => 0]],
            ]],
        ]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::sole()->reviews()->pluck('reviewer')->all())->toBe(['carol']);
});

it('leaves out pending reviews and review states it does not know', function () {
    fakeGitHub([
        pullRequestNode(1, [
            'reviews' => ['nodes' => [
                ['author' => ['__typename' => 'User', 'login' => 'bob'], 'state' => 'PENDING', 'submittedAt' => '2026-10-02T08:00:00Z', 'comments' => ['totalCount' => 0]],
                ['author' => ['__typename' => 'User', 'login' => 'dave'], 'state' => 'SOMETHING_NEW', 'submittedAt' => '2026-10-02T08:30:00Z', 'comments' => ['totalCount' => 0]],
                ['author' => ['__typename' => 'User', 'login' => 'carol'], 'state' => 'APPROVED', 'submittedAt' => '2026-10-02T09:00:00Z', 'comments' => ['totalCount' => 0]],
            ]],
        ]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::sole()->reviews()->get()->map(fn ($review) => [$review->reviewer, $review->state])->all())
        ->toBe([['carol', ReviewState::Approved]]);
});

it('caches pull requests with their derived lifecycle timestamps', function () {
    fakeGitHub([
        pullRequestNode(1, [
            'createdAt' => '2026-10-01T10:00:00Z',
            'comments' => ['totalCount' => 2, 'nodes' => [
                ['author' => ['__typename' => 'User', 'login' => 'alice'], 'createdAt' => '2026-10-01T10:30:00Z'],
                ['author' => ['__typename' => 'User', 'login' => 'bob'], 'createdAt' => '2026-10-01T12:00:00Z'],
            ]],
            'reviews' => ['nodes' => [
                ['author' => ['__typename' => 'Bot', 'login' => 'copilot'], 'state' => 'COMMENTED', 'submittedAt' => '2026-10-01T10:05:00Z', 'comments' => ['totalCount' => 4]],
                ['author' => ['__typename' => 'User', 'login' => 'carol'], 'state' => 'CHANGES_REQUESTED', 'submittedAt' => '2026-10-01T14:00:00Z', 'comments' => ['totalCount' => 2]],
                ['author' => ['__typename' => 'User', 'login' => 'carol'], 'state' => 'APPROVED', 'submittedAt' => '2026-10-02T09:00:00Z', 'comments' => ['totalCount' => 0]],
            ]],
        ]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $pullRequest = PullRequest::sole();

    expect($pullRequest->number)->toBe(1)
        ->and($pullRequest->state)->toBe(PullRequestState::Merged)
        ->and($pullRequest->first_commit_at->toIso8601ZuluString())->toBe('2026-10-01T08:00:00Z')
        ->and($pullRequest->ready_at->toIso8601ZuluString())->toBe('2026-10-01T10:00:00Z')
        ->and($pullRequest->first_response_at->toIso8601ZuluString())->toBe('2026-10-01T12:00:00Z')
        ->and($pullRequest->first_review_at->toIso8601ZuluString())->toBe('2026-10-01T14:00:00Z')
        ->and($pullRequest->first_approval_at->toIso8601ZuluString())->toBe('2026-10-02T09:00:00Z')
        ->and($pullRequest->review_comments_count)->toBe(6)
        ->and($pullRequest->checks_state)->toBe(ChecksState::Success)
        ->and($pullRequest->reviews)->toHaveCount(3);

    expect($this->repository->sync->fresh())
        ->status->toBe(RepositorySyncStatus::Succeeded)
        ->default_branch->toBe('main')
        ->description->toBe('The app')
        ->is_private->toBeTrue();
});

it('uses the ready for review event for pull requests opened as drafts', function () {
    fakeGitHub([
        pullRequestNode(2, [
            'state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null,
            'draftEvents' => ['nodes' => [['__typename' => 'ReadyForReviewEvent', 'createdAt' => '2026-10-03T09:00:00Z']]],
        ]),
        pullRequestNode(3, ['state' => 'OPEN', 'isDraft' => true, 'mergedAt' => null, 'closedAt' => null]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::firstWhere('number', 2)->ready_at->toIso8601ZuluString())->toBe('2026-10-03T09:00:00Z')
        ->and(PullRequest::firstWhere('number', 3)->ready_at)->toBeNull();
});

it('records when each pull request was a draft', function () {
    $ready = fn (string $at): array => ['__typename' => 'ReadyForReviewEvent', 'createdAt' => $at];
    $draft = fn (string $at): array => ['__typename' => 'ConvertToDraftEvent', 'createdAt' => $at];
    $open = ['state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null];

    fakeGitHub([
        pullRequestNode(4, [...$open, 'isDraft' => true, 'draftEvents' => ['nodes' => [$draft('2026-10-02T10:00:00Z'), $ready('2026-10-03T10:00:00Z'), $draft('2026-10-04T10:00:00Z')]]]),
        pullRequestNode(5, [...$open, 'draftEvents' => ['nodes' => [$ready('2026-10-03T10:00:00Z')]]]),
        pullRequestNode(6, [...$open, 'isDraft' => true]),
        pullRequestNode(7, $open),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $at = fn (string $moment): int => CarbonImmutable::parse($moment)->getTimestamp();

    expect(PullRequest::firstWhere('number', 4)->draft_intervals)->toBe([
        ['from' => $at('2026-10-02T10:00:00Z'), 'until' => $at('2026-10-03T10:00:00Z')],
        ['from' => $at('2026-10-04T10:00:00Z'), 'until' => null],
    ])
        ->and(PullRequest::firstWhere('number', 5)->draft_intervals)->toBe([['from' => $at('2026-10-01T10:00:00Z'), 'until' => $at('2026-10-03T10:00:00Z')]])
        ->and(PullRequest::firstWhere('number', 6)->draft_intervals)->toBe([['from' => $at('2026-10-01T10:00:00Z'), 'until' => null]])
        ->and(PullRequest::firstWhere('number', 7)->draft_intervals)->toBe([]);
});

it('fetches open pull requests again that were cached before their draft intervals were recorded', function () {
    $node = pullRequestNode(8, ['state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null, 'updatedAt' => '2026-10-02T10:00:00Z']);
    PullRequest::factory()->for($this->repository)->create(['number' => 8, 'state' => PullRequestState::Open, 'last_activity_at' => '2026-10-02T10:00:00Z', 'draft_intervals' => null]);
    $this->repository->syncState()->update(['pull_requests_synced_at' => now()->subHour()]);

    fakeGitHub(updated: [], open: [$node]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::firstWhere('number', 8)->draft_intervals)->toBe([]);
});

it('closes cached open pull requests that GitHub no longer reports as open', function () {
    PullRequest::factory()->for($this->repository)->create(['number' => 9, 'state' => PullRequestState::Open]);

    fakeGitHub(updated: [], open: [pullRequestNode(10, ['state' => 'OPEN', 'mergedAt' => null, 'closedAt' => null])]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::firstWhere('number', 9)->state)->toBe(PullRequestState::Closed)
        ->and(PullRequest::firstWhere('number', 10)->state)->toBe(PullRequestState::Open);
});

it('stops paginating once pull requests are older than the last sync', function () {
    $this->repository->syncState()->update(['pull_requests_synced_at' => '2026-10-05 00:00:00']);

    fakeGitHub([
        pullRequestNode(20, ['updatedAt' => '2026-10-06T10:00:00Z']),
        pullRequestNode(21, ['updatedAt' => '2026-10-01T10:00:00Z']),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::pluck('number')->all())->toBe([20]);
});

it('caches workflow runs and upserts them on re-sync', function () {
    $run = [
        'id' => 555, 'workflow_id' => 7, 'name' => 'tests', 'event' => 'push', 'status' => 'completed',
        'conclusion' => 'failure', 'head_branch' => 'main', 'head_sha' => 'abc', 'run_attempt' => 2,
        'pull_requests' => [], 'html_url' => 'https://github.com/acme/app/actions/runs/555',
        'run_started_at' => '2026-10-06T10:00:00Z', 'updated_at' => '2026-10-06T10:04:00Z', 'created_at' => '2026-10-06T10:00:00Z',
    ];

    $conclusion = 'failure';

    fakeGitHub([], [], function () use ($run, &$conclusion): array {
        return [[...$run, 'conclusion' => $conclusion]];
    });

    app(RepositorySynchronizer::class)->sync($this->repository);
    expect(WorkflowRun::sole()->conclusion)->toBe(WorkflowRunConclusion::Failure);

    $conclusion = 'success';
    app(RepositorySynchronizer::class)->sync($this->repository);

    $cached = WorkflowRun::sole();

    expect($cached->conclusion)->toBe(WorkflowRunConclusion::Success)
        ->and($cached->run_attempt)->toBe(2)
        ->and($cached->duration_seconds)->toBe(240);
});

it('splits workflow run windows that exceed the GitHub search limit', function () {
    $requestedWindows = [];

    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => function (Request $request) use (&$requestedWindows) {
            $requestedWindows[] = $request['created'];

            return Http::response(['total_count' => count($requestedWindows) === 1 ? 1500 : 0, 'workflow_runs' => []]);
        },
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect($requestedWindows)->toHaveCount(3);
});

it('fetches every page of a workflow run window', function () {
    $pages = [];

    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => function (Request $request) use (&$pages) {
            $page = (int) $request['page'];

            if ((int) $request['per_page'] === 100) {
                $pages[] = $page;
            }

            $runs = array_map(fn (int $offset): array => [
                'id' => $page * 1000 + $offset, 'workflow_id' => 1, 'name' => 'tests', 'event' => 'push', 'status' => 'completed',
                'conclusion' => 'success', 'head_branch' => 'main', 'head_sha' => 'abc', 'run_attempt' => 1, 'pull_requests' => [],
                'html_url' => 'https://github.com/acme/app/actions/runs/1', 'run_started_at' => '2026-10-06T10:00:00Z',
                'updated_at' => '2026-10-06T10:05:00Z', 'created_at' => '2026-10-06T10:00:00Z',
            ], range(1, $page === 3 ? 50 : 100));

            return Http::response(['total_count' => 250, 'workflow_runs' => $runs]);
        },
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect($pages)->toEqualCanonicalizing([1, 2, 3])
        ->and(WorkflowRun::count())->toBe(250);
});

it('re-fetches workflow run pages that come back short', function () {
    $calls = [];

    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => function (Request $request) use (&$calls) {
            $page = (int) $request['page'];
            $calls[$page] = ($calls[$page] ?? 0) + 1;
            $size = $page === 1 ? 100 : ($calls[$page] === 1 ? 10 : 30);

            return Http::response(['total_count' => 130, 'workflow_runs' => array_map(fn (int $offset): array => [
                'id' => $page * 1000 + $offset, 'workflow_id' => 1, 'name' => 'tests', 'event' => 'push', 'status' => 'completed',
                'conclusion' => 'success', 'head_branch' => 'main', 'head_sha' => 'abc', 'run_attempt' => 1, 'pull_requests' => [],
                'html_url' => 'https://github.com/acme/app/actions/runs/1', 'run_started_at' => '2026-10-06T10:00:00Z',
                'updated_at' => '2026-10-06T10:05:00Z', 'created_at' => '2026-10-06T10:00:00Z',
            ], range(1, $size))]);
        },
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(WorkflowRun::count())->toBe(130)
        ->and($calls[2])->toBe(2);
});

it('fails the sync instead of silently dropping workflow runs', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => Http::response(['total_count' => 250, 'workflow_runs' => []]),
    ]);

    expect(fn () => app(RepositorySynchronizer::class)->sync($this->repository))
        ->toThrow(GitHubException::class, 'GitHub returned 0 of 100 workflow runs');
});

it('lets the concurrent tasks print their results under a quiet queue worker', function () {
    // `queue:work --quiet` sets SHELL_VERBOSITY=-1, which would silence the task processes.
    putenv('SHELL_VERBOSITY=-1');
    $_ENV['SHELL_VERBOSITY'] = $_SERVER['SHELL_VERBOSITY'] = '-1';

    $verbosityDuringTasks = null;

    fakeGitHub([], runs: function () use (&$verbosityDuringTasks): array {
        $verbosityDuringTasks = [getenv('SHELL_VERBOSITY'), $_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']];

        return [];
    });

    try {
        app(RepositorySynchronizer::class)->sync($this->repository);

        expect($verbosityDuringTasks)->toBe(['0', '0', '0'])
            ->and([getenv('SHELL_VERBOSITY'), $_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']])->toBe(['-1', '-1', '-1']);
    } finally {
        putenv('SHELL_VERBOSITY');
        unset($_ENV['SHELL_VERBOSITY'], $_SERVER['SHELL_VERBOSITY']);
    }
});

it('trusts the count a workflow run page reports over the window probe', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => fn (Request $request) => Http::response([
            'total_count' => (int) $request['per_page'] === 1 ? 6 : 0,
            'workflow_runs' => [],
        ]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect($this->repository->syncState()->status)->toBe(RepositorySyncStatus::Succeeded)
        ->and(WorkflowRun::count())->toBe(0);
});

it('splits the pull request search into slices that are fetched concurrently', function () {
    $searches = [];

    Http::fake([
        'api.github.com/graphql' => function (Request $request) use (&$searches) {
            if (str_contains($request['query'], 'search(query: $query')) {
                $searches[] = $request['variables']['query'];
            }

            // Pretend the whole lookback holds 250 pull requests, so it is split up.
            return fakeGraphql(
                [pullRequestNode(1, ['updatedAt' => '2026-10-06T10:00:00Z']), pullRequestNode(2, ['updatedAt' => '2026-06-01T10:00:00Z'])],
                count: fn (string $search): ?int => str_contains($search, 'updated:2026-04-10T00:00:00Z..2026-10-07T12:00:00Z') ? 250 : null,
            )($request);
        },
        'api.github.com/repos/acme/app/actions/runs*' => Http::response(['total_count' => 0, 'workflow_runs' => []]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::orderBy('number')->pluck('number')->all())->toBe([1, 2])
        ->and($searches)->toHaveCount(2)
        ->and($searches)->each->not->toContain('2026-04-10T00:00:00Z..2026-10-07T12:00:00Z');
});

it('resumes an interrupted pull request backfill below the slices it stored', function () {
    $searches = [];
    $olderSlicesFail = true;
    $updated = [pullRequestNode(1, ['updatedAt' => '2026-10-06T10:00:00Z']), pullRequestNode(2, ['updatedAt' => '2026-05-01T10:00:00Z'])];

    Http::fake([
        'api.github.com/graphql' => function (Request $request) use (&$searches, &$olderSlicesFail, $updated) {
            if (str_contains($request['query'], 'search(query: $query')) {
                $searches[] = $request['variables']['query'];
            }

            // The whole lookback splits into 20 slices that each claim a pull request, so they are
            // fetched over several rounds.
            return fakeGraphql(
                $updated,
                count: fn (string $search): int => str_contains($search, '2026-04-10T00:00:00Z..2026-10-07T12:00:00Z') ? 1000 : 1,
                fails: fn (string $search): bool => $olderSlicesFail && searchPullRequests([$updated[1]], $search) !== [],
            )($request);
        },
        'api.github.com/repos/acme/app/actions/runs*' => Http::response(['total_count' => 0, 'workflow_runs' => []]),
    ]);

    expect(fn () => app(RepositorySynchronizer::class)->sync($this->repository))->toThrow(GitHubException::class);

    $checkpoint = RepositorySync::sole()->pull_requests_reached_at;

    expect(PullRequest::pluck('number')->all())->toBe([1])
        ->and($checkpoint->between('2026-05-01 10:00:00', '2026-10-06 10:00:00'))->toBeTrue();

    $olderSlicesFail = false;
    $searches = [];
    CarbonImmutable::setTestNow('2026-10-07 13:00:00');

    app(RepositorySynchronizer::class)->sync($this->repository);

    $state = RepositorySync::sole();

    expect(PullRequest::orderBy('number')->pluck('number')->all())->toBe([1, 2])
        ->and(collect($searches)->every(fn (string $search): bool => CarbonImmutable::parse(Str::afterLast($search, '..'))->lte($checkpoint)))->toBeTrue()
        ->and($state->pull_requests_synced_at)->toEqual(CarbonImmutable::parse('2026-10-07 12:00:00'))
        ->and($state->pull_requests_reached_at)->toBeNull()
        ->and($state->pull_requests_resume_started_at)->toBeNull();
});

it('only looks up open pull requests that are missing or out of date', function () {
    PullRequest::factory()->for($this->repository)->create(['number' => 41, 'state' => PullRequestState::Open, 'last_activity_at' => '2025-01-01 10:00:00', 'draft_intervals' => []]);
    PullRequest::factory()->for($this->repository)->create(['number' => 42, 'state' => PullRequestState::Open, 'last_activity_at' => '2025-01-01 10:00:00', 'draft_intervals' => []]);

    fakeGitHub(updated: [], open: [
        pullRequestNode(41, ['state' => 'OPEN', 'updatedAt' => '2025-01-01T10:00:00Z', 'mergedAt' => null, 'closedAt' => null]),
        pullRequestNode(42, ['state' => 'OPEN', 'updatedAt' => '2025-02-01T10:00:00Z', 'mergedAt' => null, 'closedAt' => null, 'title' => 'Renamed']),
        pullRequestNode(43, ['state' => 'OPEN', 'updatedAt' => '2025-01-01T10:00:00Z', 'mergedAt' => null, 'closedAt' => null]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::orderBy('number')->pluck('number')->all())->toBe([41, 42, 43])
        ->and(PullRequest::firstWhere('number', 42)->title)->toBe('Renamed');

    Http::assertSent(fn (Request $request): bool => str_contains($request['query'] ?? '', 'pr42: pullRequest') && str_contains($request['query'], 'pr43: pullRequest') && ! str_contains($request['query'], 'pr41: pullRequest'));
});

it('resumes an interrupted workflow run backfill after the last stored window', function () {
    $requestedWindows = [];
    $laterWindowsFail = true;

    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => function (Request $request) use (&$requestedWindows, &$laterWindowsFail) {
            $requestedWindows[] = $request['created'];

            return match (true) {
                count($requestedWindows) === 1 => Http::response(['total_count' => 1500, 'workflow_runs' => []]),
                count($requestedWindows) === 2 || ! $laterWindowsFail => Http::response(['total_count' => 0, 'workflow_runs' => []]),
                default => Http::response(['total_count' => 1, 'workflow_runs' => []]),
            };
        },
    ]);

    expect(fn () => app(RepositorySynchronizer::class)->sync($this->repository))->toThrow(GitHubException::class);

    [, $middle] = explode('..', $requestedWindows[1]);
    $checkpoint = RepositorySync::sole()->workflow_runs_synced_at;

    expect($checkpoint)->toEqual(CarbonImmutable::parse($middle));

    $laterWindowsFail = false;
    $requestedWindows = [];

    app(RepositorySynchronizer::class)->sync($this->repository);

    [$resumedFrom] = explode('..', $requestedWindows[0]);

    expect(CarbonImmutable::parse($resumedFrom))->toEqual($checkpoint->subDays(2));
});

it('caches commits, force pushes and comments as pull request activity', function () {
    fakeGitHub([
        pullRequestNode(30, [
            'comments' => ['totalCount' => 1, 'nodes' => [
                ['author' => ['__typename' => 'User', 'login' => 'bob'], 'createdAt' => '2026-10-01T12:00:00Z'],
            ]],
            'activity' => ['nodes' => [
                ['__typename' => 'PullRequestCommit', 'commit' => ['committedDate' => '2026-10-01T09:00:00Z', 'author' => ['user' => ['login' => 'alice']]]],
                ['__typename' => 'HeadRefForcePushedEvent', 'createdAt' => '2026-10-01T13:00:00Z', 'actor' => ['__typename' => 'User', 'login' => 'alice']],
            ]],
        ]),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::sole()->activities()->orderBy('occurred_at')->get(['kind', 'actor'])->toArray())->toBe([
        ['kind' => 'commit', 'actor' => 'alice'],
        ['kind' => 'comment', 'actor' => 'bob'],
        ['kind' => 'force_push', 'actor' => 'alice'],
    ]);
});

it('records how long first attempts of workflow runs queued', function () {
    $run = fn (int $id, int $attempt): array => [
        'id' => $id, 'workflow_id' => 7, 'name' => 'tests', 'event' => 'push', 'status' => 'completed',
        'conclusion' => 'success', 'head_branch' => 'main', 'head_sha' => 'abc', 'run_attempt' => $attempt,
        'pull_requests' => [], 'html_url' => 'https://github.com/acme/app/actions/runs/1',
        'created_at' => '2026-10-06T10:00:00Z', 'run_started_at' => '2026-10-06T10:02:30Z', 'updated_at' => '2026-10-06T10:10:00Z',
    ];

    fakeGitHub([], [], [$run(1, 1), $run(2, 2)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(WorkflowRun::orderBy('github_id')->pluck('queue_seconds')->all())->toBe([150, null]);
});

it('only fetches the full list of open pull requests on a backfill', function () {
    $this->repository->syncState()->update(['pull_requests_synced_at' => '2026-10-05 00:00:00', 'workflow_runs_synced_at' => '2026-10-05 00:00:00']);

    fakeGitHub([pullRequestNode(40, ['updatedAt' => '2026-10-06T10:00:00Z'])], open: [pullRequestNode(41, ['state' => 'OPEN'])]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::pluck('number')->all())->toBe([40]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request['query'] ?? '', 'states: OPEN'));
});

it('warms the report cache after a sync', function () {
    fakeGitHub([pullRequestNode(50)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $version = $this->repository->sync->fresh()->finished_at->getTimestamp().':'.$this->repository->fresh()->updated_at->getTimestamp();

    expect(Cache::get("houston:report:full:{$this->repository->id}:30@day:0")['version'])->toEndWith(":{$version}")
        ->and(Cache::get("houston:report:summary:{$this->repository->id}:30@day:0")['version'])->toEndWith(":{$version}")
        ->and(Cache::get("houston:report:wall:{$this->repository->id}:14@day:0")['version'])->toEndWith(":{$version}");
});

it('warms the report cache with the report defaults', function () {
    (new ReportDefaults(90, Granularity::Week, true))->save();
    fakeGitHub([pullRequestNode(50)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(Cache::has("houston:report:full:{$this->repository->id}:90@week-complete:0"))->toBeTrue()
        ->and(Cache::has("houston:report:summary:{$this->repository->id}:90@week:0"))->toBeTrue();
});

it('warms the repository report for each period and grouping that fits it', function () {
    (new ReportDefaults(30, Granularity::Day, withoutCurrentBucket: true))->save();
    fakeGitHub([pullRequestNode(50)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $warmed = fn (string $period): bool => Cache::has("houston:report:full:{$this->repository->id}:{$period}:0");

    expect(collect(['7@day', '14@day', '14@week', '30@day', '30@week', '45@day', '45@week', '90@day', '90@week', '90@month', '180@day', '180@week', '180@month', '365@day', '365@week', '365@month'])->map(fn (string $period): string => "{$period}-complete")->reject($warmed)->all())->toBe([])
        ->and($warmed('90@month'))->toBeFalse()
        ->and($warmed('365@week'))->toBeFalse();
});

it('leaves warming the reports to a queued job', function () {
    Queue::fake();
    fakeGitHub([pullRequestNode(50)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    Queue::assertPushed(WarmReportCache::class, fn (WarmReportCache $job): bool => $job->repository->is($this->repository));
    expect(Cache::has("houston:report:full:{$this->repository->id}:30@day:0"))->toBeFalse();
});

it('leaves checking the dependencies to a queued job', function () {
    Queue::fake();
    fakeGitHub([pullRequestNode(50)]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    Queue::assertPushed(CheckDependencies::class, fn (CheckDependencies $job): bool => $job->repository->is($this->repository));
});

it('serves a cached report until the repository is synced again', function () {
    $reports = app(ReportCache::class);
    $this->repository->syncState()->update(['finished_at' => now()->subHour()]);
    $repository = $this->repository->fresh('sync');

    $first = $reports->full($repository, Period::lastDays(30, 'UTC'));
    PullRequest::factory()->for($repository)->create(['merged_at' => now()->subDay()]);

    expect($reports->full($repository, Period::lastDays(30, 'UTC'))['kpis'])->toBe($first['kpis']);

    $repository->sync->update(['finished_at' => now()]);

    expect($reports->full($repository->fresh('sync'), Period::lastDays(30, 'UTC'))['kpis'])->not->toBe($first['kpis']);
});

it('rebuilds reports cached before the report format changed', function () {
    $this->repository->syncState()->update(['finished_at' => now()->subHour()]);
    $repository = $this->repository->fresh('sync');
    $version = "1:{$repository->sync->finished_at->getTimestamp()}:{$repository->updated_at->getTimestamp()}";

    Cache::put("houston:report:full:{$repository->id}:30@day:0", ['version' => $version, 'report' => ['kpis' => []]]);

    expect(app(ReportCache::class)->full($repository, Period::lastDays(30, 'UTC'))['kpis'])->not->toBeEmpty();
});

it('records the progress of a backfill as it goes', function () {
    fakeGitHub([pullRequestNode(1), pullRequestNode(2, ['updatedAt' => '2026-10-01T10:00:00Z'])]);

    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    app(RepositorySynchronizer::class)->sync($this->repository);

    expect($this->repository->sync->fresh())
        ->backfill->toBeTrue()
        ->phase->toBeNull()
        ->pull_requests_fetched->toBe(2)
        ->pull_requests_reached_at->toBeNull()
        ->workflow_runs_reached_at->toEqual(CarbonImmutable::parse('2026-10-07 12:00:00'))
        ->last_duration_seconds->toBe(0);
});

it('keeps the phase a sync failed in', function () {
    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['repository' => [
            'description' => null, 'defaultBranchRef' => ['name' => 'main'], 'owner' => ['avatarUrl' => null],
            'pullRequests' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => []],
        ]]]),
        'api.github.com/repos/acme/app/actions/runs*' => Http::response(['total_count' => 5, 'workflow_runs' => []]),
    ]);

    expect(fn () => app(RepositorySynchronizer::class)->sync($this->repository))->toThrow(GitHubException::class);

    expect($this->repository->sync->fresh()->phase)->toBe(RepositorySyncPhase::WorkflowRuns);
});

it('backfills as far back as the repository lookback asks for', function () {
    $this->repository->update(['lookback_days' => 30]);

    fakeGitHub([
        pullRequestNode(1, ['updatedAt' => '2026-10-01T10:00:00Z']),
        pullRequestNode(2, ['updatedAt' => '2026-08-01T10:00:00Z']),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect(PullRequest::pluck('number')->all())->toBe([1])
        ->and($this->repository->sync->fresh()->history_from)->toEqual(CarbonImmutable::parse('2026-09-07'));
});

it('fetches the older history once the lookback is extended', function () {
    $this->repository->update(['lookback_days' => 365]);
    $this->repository->syncState()->update([
        'pull_requests_synced_at' => '2026-10-07 11:00:00',
        'workflow_runs_synced_at' => '2026-10-07 11:00:00',
        'history_from' => '2026-04-10',
    ]);

    fakeGitHub([
        pullRequestNode(20, ['updatedAt' => '2026-10-06T10:00:00Z']),
        pullRequestNode(21, ['updatedAt' => '2026-01-01T10:00:00Z']),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $state = $this->repository->sync->fresh();

    expect(PullRequest::pluck('number')->all())->toBe([20, 21])
        ->and($state->backfill)->toBeTrue()
        ->and($state->workflow_runs_from)->toEqual(CarbonImmutable::parse('2025-10-07'))
        ->and($state->history_from)->toEqual(CarbonImmutable::parse('2025-10-07'));
});

it('backfills back to when the repository was created for an all-time lookback', function () {
    $this->repository->update(['lookback_days' => Repository::AllTime]);

    fakeGitHub([
        pullRequestNode(1, ['updatedAt' => '2026-10-01T10:00:00Z']),
        pullRequestNode(2, ['updatedAt' => '2024-06-01T10:00:00Z']),
    ]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    $state = $this->repository->sync->fresh();

    expect(PullRequest::pluck('number')->sort()->values()->all())->toBe([1, 2])
        ->and($state->repository_created_at)->toEqual(CarbonImmutable::parse('2024-03-15T09:00:00Z'))
        ->and($state->history_from)->toEqual(CarbonImmutable::parse('2024-03-15'))
        ->and($this->repository->fresh()->needsBackfill())->toBeFalse();
});

it('keeps the cached history when the lookback is shortened', function () {
    $this->repository->update(['lookback_days' => 30]);
    $this->repository->syncState()->update(['pull_requests_synced_at' => '2026-10-07 11:00:00', 'history_from' => '2026-04-10']);

    fakeGitHub([]);

    app(RepositorySynchronizer::class)->sync($this->repository);

    expect($this->repository->sync->fresh())
        ->backfill->toBeFalse()
        ->history_from->toEqual(CarbonImmutable::parse('2026-04-10'));
});
