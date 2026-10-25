<?php

namespace App\GitHub;

use App\Enums\ChecksState;
use App\Enums\PullRequestActivityKind;
use App\Enums\PullRequestState;
use App\Enums\RepositorySyncPhase;
use App\Enums\RepositorySyncStatus;
use App\Enums\ReviewState;
use App\Enums\SystemEventType;
use App\Enums\WorkflowRunConclusion;
use App\Enums\WorkflowRunStatus;
use App\Jobs\CheckDependencies;
use App\Jobs\SyncRepository;
use App\Jobs\WarmReportCache;
use App\Models\Repository;
use App\Models\RepositorySync;
use App\Models\SystemEvent;
use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Pulls pull requests, reviews and workflow runs from GitHub into the local cache.
 *
 * Every table written here is disposable: wiping it simply makes the next sync a full backfill.
 */
class RepositorySynchronizer
{
    /**
     * Everything stored about a pull request. Reviews and timeline items are capped well above what
     * typical pull requests have, since GitHub answers noticeably slower the more nested nodes it may return.
     */
    private const string PullRequestFields = <<<'GRAPHQL'
        fragment PullRequestFields on PullRequest {
          number title url state isDraft createdAt updatedAt mergedAt closedAt
          additions deletions changedFiles baseRefName headRefName
          author { __typename login avatarUrl }
          comments(first: 30) { totalCount nodes { author { __typename login } createdAt } }
          commits { totalCount }
          firstCommit: commits(first: 1) { nodes { commit { authoredDate committedDate } } }
          lastCommit: commits(last: 1) { nodes { commit { statusCheckRollup { state } } } }
          labels(first: 10) { nodes { name color } }
          reviews(first: 30) { nodes { author { __typename login } state submittedAt comments { totalCount } } }
          draftEvents: timelineItems(first: 20, itemTypes: [READY_FOR_REVIEW_EVENT, CONVERT_TO_DRAFT_EVENT]) {
            nodes {
              __typename
              ... on ReadyForReviewEvent { createdAt }
              ... on ConvertToDraftEvent { createdAt }
            }
          }
          activity: timelineItems(last: 50, itemTypes: [PULL_REQUEST_COMMIT, HEAD_REF_FORCE_PUSHED_EVENT]) {
            nodes {
              __typename
              ... on PullRequestCommit { commit { committedDate author { user { login } } } }
              ... on HeadRefForcePushedEvent { createdAt actor { __typename login } }
            }
          }
        }
        GRAPHQL;

    private const string PullRequestSearchQuery = <<<'GRAPHQL'
        query($query: String!, $cursor: String) {
          search(query: $query, type: ISSUE, first: 50, after: $cursor) {
            pageInfo { hasNextPage endCursor }
            nodes { ... on PullRequest { ...PullRequestFields } }
          }
        }
        GRAPHQL;

    private const string OpenPullRequestsQuery = <<<'GRAPHQL'
        query($owner: String!, $name: String!, $cursor: String) {
          repository(owner: $owner, name: $name) {
            pullRequests(first: 100, after: $cursor, states: OPEN) {
              pageInfo { hasNextPage endCursor }
              nodes { number updatedAt }
            }
          }
        }
        GRAPHQL;

    private const string RepositoryQuery = <<<'GRAPHQL'
        query($owner: String!, $name: String!) {
          repository(owner: $owner, name: $name) {
            description
            createdAt
            isPrivate
            defaultBranchRef { name }
            owner { avatarUrl }
          }
        }
        GRAPHQL;

    /**
     * Pull request searches are split into time slices of about this many pull requests, which are
     * then fetched concurrently. A single search pages through its results one cursor at a time.
     */
    private const int PullRequestSliceSize = 100;

    /**
     * How many searches are counted in one query; each count costs next to nothing.
     */
    private const int SearchCountsPerQuery = 40;

    /**
     * How many pull requests are looked up by number in one query.
     */
    private const int PullRequestsPerLookup = 25;

    private const int GraphqlConcurrency = 6;

    private const int RestConcurrency = 10;

    /**
     * GitHub caps every filtered workflow-run search at this many results.
     */
    private const int RunSearchLimit = 1000;

    public function __construct(
        private GitHubClient $github,
    ) {}

    /**
     * Refresh the cache for one repository. Only one sync per repository runs at a time, whether it
     * was started by the queue, the scheduler or the CLI.
     *
     * @throws SyncInProgressException
     */
    public function sync(Repository $repository): void
    {
        $lock = Cache::lock("houston:sync:{$repository->id}", SyncRepository::MaxRuntimeSeconds);

        if (! $lock->get()) {
            throw new SyncInProgressException("{$repository->fullName()} is already being synced.");
        }

        try {
            $this->run($repository);
        } finally {
            $lock->release();
        }

        WarmReportCache::dispatch($repository);
        CheckDependencies::dispatch($repository);
    }

    private function run(Repository $repository): void
    {
        $state = $repository->syncState();
        $repository->setRelation('sync', $state);
        $startedAt = CarbonImmutable::now();
        $lookbackStart = $repository->lookbackStart();
        $backfill = $repository->needsBackfill();

        $state->update([
            'status' => RepositorySyncStatus::Running,
            'rate_limited_until' => null,
            'started_at' => $startedAt,
            'error' => null,
            'backfill' => $backfill,
            'pull_requests_fetched' => 0,
            'workflow_runs_fetched' => 0,
        ]);

        SystemEvent::record(SystemEventType::SyncStarted, ($backfill ? 'Started backfilling ' : 'Started syncing ').$repository->fullName(), $repository, [
            'backfill' => $backfill,
            'lookback_from' => $lookbackStart->toIso8601String(),
        ]);

        $state->enterPhase(RepositorySyncPhase::Details);
        $this->syncRepositoryDetails($repository, $state);

        // An all-time lookback reaches back to when the repository was created, which is only known now.
        $lookbackStart = $repository->lookbackStart();

        // Pull requests, workflow runs and deployments don't depend on each other, and workflow runs use the
        // REST API's separate rate limit, so all three are fetched at once in their own processes. Each
        // writes only its own columns; the phase column follows the pull requests, which take the longest.
        $id = $repository->id;

        Concurrency::run(array_map(self::returnsItsErrors(...), [
            static function () use ($id, $startedAt, $lookbackStart, $backfill): void {
                [$repository, $state] = self::load($id);

                app(self::class)->syncPullRequests($repository, $state, $startedAt, $lookbackStart, $backfill);

                // Whatever is left of the sync is waiting for the workflow runs and deployments.
                $state->enterPhase(RepositorySyncPhase::WorkflowRuns);
            },
            static function () use ($id, $startedAt, $lookbackStart, $backfill): void {
                [$repository, $state] = self::load($id);

                app(self::class)->syncWorkflowRuns($repository, $state, $startedAt, $lookbackStart, $backfill);
            },
            static function () use ($id, $startedAt, $lookbackStart, $backfill): void {
                [$repository, $state] = self::load($id);

                app(DeploymentSynchronizer::class)->sync($repository, $state, $startedAt, $lookbackStart, $backfill);
            },
        ]), SyncRepository::MaxRuntimeSeconds);

        $state->refresh();

        $finishedAt = CarbonImmutable::now();

        $state->update([
            'status' => RepositorySyncStatus::Succeeded,
            'phase' => null,
            'phase_started_at' => null,
            'finished_at' => $finishedAt,
            'history_from' => $backfill ? $lookbackStart : $state->coveredFrom(),
            'last_duration_seconds' => (int) $startedAt->diffInSeconds($finishedAt),
        ]);

        SystemEvent::record(SystemEventType::SyncFinished, "Finished syncing {$repository->fullName()}", $repository, [
            'seconds' => $state->last_duration_seconds,
            'pull_requests_fetched' => $state->pull_requests_fetched,
            'workflow_runs_fetched' => $state->workflow_runs_fetched,
            'backfill' => $backfill,
        ]);
    }

    /**
     * A task's exception travels back from its process as JSON and is rebuilt from its constructor arguments.
     * Many can't make that trip: an HTTP client exception comes back as a type error, and one that can't be
     * encoded at all (a query exception whose bindings aren't valid UTF-8) as "Trying to access array offset
     * on null". So anything but GitHub's own exceptions and the ones the sync job handles is reported where it happened and sent
     * back as a plain one that carries just the message.
     *
     * @param  Closure(): void  $task
     * @return Closure(): void
     */
    private static function returnsItsErrors(Closure $task): Closure
    {
        return static function () use ($task): void {
            try {
                $task();
            } catch (GitHubException|SyncInProgressException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);

                throw new RuntimeException(mb_scrub($exception::class.': '.$exception->getMessage()));
            }
        };
    }

    /**
     * Load a repository and its sync state afresh, as a concurrent process has to.
     *
     * @return array{Repository, RepositorySync}
     */
    private static function load(int $id): array
    {
        $repository = Repository::findOrFail($id);
        $state = $repository->syncState();
        $repository->setRelation('sync', $state);

        return [$repository, $state];
    }

    private function syncRepositoryDetails(Repository $repository, RepositorySync $state): void
    {
        $details = $this->github->graphql(self::RepositoryQuery, [
            'owner' => $repository->owner,
            'name' => $repository->name,
        ])['repository'] ?? null;

        if ($details === null) {
            throw new GitHubException("Repository {$repository->fullName()} was not found or is not accessible.");
        }

        $state->update([
            'default_branch' => data_get($details, 'defaultBranchRef.name'),
            'description' => str($details['description'] ?? '')->limit(250)->toString() ?: null,
            'avatar_url' => data_get($details, 'owner.avatarUrl'),
            'is_private' => $details['isPrivate'] ?? null,
            'repository_created_at' => isset($details['createdAt']) ? CarbonImmutable::parse($details['createdAt']) : null,
        ]);
    }

    /**
     * @param  bool  $backfill  Walk back to the lookback start instead of the last sync, e.g. after the lookback was extended.
     */
    private function syncPullRequests(Repository $repository, RepositorySync $state, CarbonImmutable $startedAt, CarbonImmutable $lookbackStart, bool $backfill): void
    {
        $isFullSync = $state->pull_requests_synced_at === null;
        $watermark = $backfill ? $lookbackStart : ($state->pull_requests_synced_at?->subHour() ?? $lookbackStart);

        // A pass that stopped part way resumes below the newest slices it had fully stored. It keeps its
        // original start time, so pull requests updated while it was interrupted are picked up by the next pass.
        $passStartedAt = $state->pull_requests_resume_started_at ?? $startedAt;
        $state->update(['pull_requests_resume_started_at' => $passStartedAt, 'pull_requests_from' => $watermark]);
        $state->enterPhase(RepositorySyncPhase::PullRequests);

        $slices = $this->pullRequestSlices($repository, $watermark, $state->pull_requests_reached_at ?? $passStartedAt);
        $this->fetchPullRequestSlices($repository, $state, $slices);

        // A backfill only reaches back to the lookback window, so long-lived open pull requests
        // are fetched separately. Afterwards any change to them, including being closed, bumps
        // updatedAt and is picked up by the incremental pass above. Open ones cached before draft
        // intervals were recorded are fetched again once to get them.
        $openWithoutDraftIntervals = $repository->pullRequests()->where('state', PullRequestState::Open)->whereNull('draft_intervals')->exists();

        if ($isFullSync || $openWithoutDraftIntervals) {
            $state->enterPhase(RepositorySyncPhase::OpenPullRequests);
            $this->syncOpenPullRequests($repository, $state, $passStartedAt);
        }

        $state->update([
            'pull_requests_synced_at' => $passStartedAt,
            'pull_requests_resume_started_at' => null,
            'pull_requests_reached_at' => null,
        ]);
    }

    /**
     * Split the window into slices small enough to fetch in a page or two, newest first.
     *
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable, count: int}>
     */
    private function pullRequestSlices(Repository $repository, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $slices = [];
        $pending = [['from' => $from, 'to' => $to]];

        while ($pending !== []) {
            $counts = $this->countPullRequests($repository, $pending);
            $next = [];

            foreach ($pending as $index => $window) {
                $count = $counts[$index];

                if ($count > self::PullRequestSliceSize && $window['from']->diffInSeconds($window['to']) > 60) {
                    // Oversplit, since pull requests cluster around busy days.
                    array_push($next, ...$this->splitWindow($window['from'], $window['to'], 2 * (int) ceil($count / self::PullRequestSliceSize)));
                } else {
                    $slices[] = [...$window, 'count' => $count];
                }
            }

            $pending = $next;
        }

        usort($slices, fn (array $a, array $b): int => $b['from'] <=> $a['from']);

        return $slices;
    }

    /**
     * @param  list<array{from: CarbonImmutable, to: CarbonImmutable}>  $windows
     * @return list<int>
     */
    private function countPullRequests(Repository $repository, array $windows): array
    {
        $queries = array_map(function (array $chunk) use ($repository): array {
            $arguments = implode(', ', array_map(fn (int $index): string => "\$q{$index}: String!", array_keys($chunk)));
            $searches = implode("\n", array_map(fn (int $index): string => "s{$index}: search(query: \$q{$index}, type: ISSUE, first: 0) { issueCount }", array_keys($chunk)));
            $variables = [];

            foreach ($chunk as $index => $window) {
                $variables["q{$index}"] = $this->pullRequestSearch($repository, $window['from'], $window['to']);
            }

            return ["query({$arguments}) {\n{$searches}\n}", $variables];
        }, array_chunk($windows, self::SearchCountsPerQuery));

        $counts = [];

        foreach ($this->github->graphqlMany($queries, self::GraphqlConcurrency) as $chunk => $data) {
            for ($index = 0; $index < count($queries[$chunk][1]); $index++) {
                $counts[] = (int) data_get($data, "s{$index}.issueCount", 0);
            }
        }

        return $counts;
    }

    private function pullRequestSearch(Repository $repository, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return "repo:{$repository->fullName()} is:pr updated:{$from->toIso8601ZuluString()}..{$to->toIso8601ZuluString()}";
    }

    /**
     * Fetch the slices a few at a time, newest first. The checkpoint only moves past a slice once it and
     * every newer slice are stored, so a pass that stops part way never skips pull requests.
     *
     * @param  list<array{from: CarbonImmutable, to: CarbonImmutable, count: int}>  $slices
     */
    private function fetchPullRequestSlices(Repository $repository, RepositorySync $state, array $slices): void
    {
        $cursors = [];
        $done = array_map(fn (array $slice): bool => $slice['count'] === 0, $slices);
        $queue = array_keys(array_filter($done, fn (bool $isDone): bool => ! $isDone));
        $active = [];
        $checkpoint = 0;

        while (true) {
            while (count($active) < self::GraphqlConcurrency && $queue !== []) {
                $active[] = array_shift($queue);
            }

            if ($active !== []) {
                $pages = $this->github->graphqlMany(array_map(fn (int $index): array => [self::PullRequestSearchQuery.self::PullRequestFields, [
                    'query' => $this->pullRequestSearch($repository, $slices[$index]['from'], $slices[$index]['to']),
                    'cursor' => $cursors[$index] ?? null,
                ]], $active), self::GraphqlConcurrency);

                foreach ($active as $position => $index) {
                    $connection = $pages[$position]['search'] ?? null;

                    foreach ($connection['nodes'] ?? [] as $node) {
                        if (isset($node['number'])) {
                            $this->storePullRequest($repository, $node);
                            $state->pull_requests_fetched++;
                        }
                    }

                    if (data_get($connection, 'pageInfo.hasNextPage')) {
                        $cursors[$index] = $connection['pageInfo']['endCursor'];
                    } else {
                        $done[$index] = true;
                    }
                }

                $active = array_values(array_filter($active, fn (int $index): bool => ! $done[$index]));
            }

            while ($checkpoint < count($slices) && $done[$checkpoint]) {
                $state->pull_requests_reached_at = $slices[$checkpoint]['from'];
                $checkpoint++;
            }

            $state->save();

            if ($active === [] && $queue === []) {
                return;
            }
        }
    }

    /**
     * Store open pull requests the pass above did not reach, and close cached ones that are no longer open.
     */
    private function syncOpenPullRequests(Repository $repository, RepositorySync $state, CarbonImmutable $passStartedAt): void
    {
        $open = [];
        $cursor = null;

        do {
            $connection = $this->github->graphql(self::OpenPullRequestsQuery, [
                'owner' => $repository->owner,
                'name' => $repository->name,
                'cursor' => $cursor,
            ])['repository']['pullRequests'] ?? null;

            foreach ($connection['nodes'] ?? [] as $node) {
                $open[$node['number']] = CarbonImmutable::parse($node['updatedAt']);
            }

            $cursor = $connection['pageInfo']['endCursor'] ?? null;
        } while ($connection['pageInfo']['hasNextPage'] ?? false);

        // Open pull requests cached before draft intervals were recorded are fetched again to get them.
        $cached = $repository->pullRequests()->whereIn('number', array_keys($open))->whereNotNull('draft_intervals')->pluck('last_activity_at', 'number');

        $missing = array_keys(array_filter(
            $open,
            fn (CarbonImmutable $updatedAt, int $number): bool => ! $cached->has($number) || ! $updatedAt->equalTo($cached[$number]),
            ARRAY_FILTER_USE_BOTH,
        ));

        $lookups = array_map(fn (array $numbers): array => [
            'query($owner: String!, $name: String!) { repository(owner: $owner, name: $name) { '
                .implode(' ', array_map(fn (int $number): string => "pr{$number}: pullRequest(number: {$number}) { ...PullRequestFields }", $numbers))
                .' } }'.self::PullRequestFields,
            ['owner' => $repository->owner, 'name' => $repository->name],
        ], array_chunk($missing, self::PullRequestsPerLookup));

        foreach (array_chunk($lookups, self::GraphqlConcurrency) as $batch) {
            foreach ($this->github->graphqlMany($batch, self::GraphqlConcurrency) as $data) {
                foreach ($data['repository'] ?? [] as $node) {
                    if (is_array($node)) {
                        $this->storePullRequest($repository, $node);
                        $state->pull_requests_fetched++;
                    }
                }
            }

            $state->save();
        }

        // Anything cached as open that GitHub no longer reports as open has been closed or deleted.
        $repository->pullRequests()
            ->where('state', PullRequestState::Open)
            ->whereNotIn('number', array_keys($open))
            ->update(['state' => PullRequestState::Closed, 'closed_at' => $passStartedAt]);
    }

    /**
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function splitWindow(CarbonImmutable $from, CarbonImmutable $to, int $parts): array
    {
        $step = (int) ceil($from->diffInSeconds($to) / max(1, $parts));

        return array_map(fn (int $part): array => [
            'from' => $from->addSeconds($part * $step),
            'to' => $from->addSeconds(($part + 1) * $step)->min($to),
        ], range(0, max(1, $parts) - 1));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function storePullRequest(Repository $repository, array $node): void
    {
        $author = data_get($node, 'author.login');
        $openedAt = CarbonImmutable::parse($node['createdAt']);

        // Reviews in a state this version doesn't know yet are left out rather than failing the sync.
        $reviews = $this->nodes($node, 'reviews.nodes')
            ->filter(fn (array $review): bool => $review['submittedAt'] !== null && ! in_array(ReviewState::tryFrom($review['state']), [null, ReviewState::Pending], true))
            ->map(fn (array $review): array => [
                'reviewer' => data_get($review, 'author.login') ?? 'ghost',
                'reviewer_is_bot' => $this->isBot(data_get($review, 'author')),
                'state' => ReviewState::from($review['state']),
                'comments_count' => data_get($review, 'comments.totalCount', 0),
                'submitted_at' => CarbonImmutable::parse($review['submittedAt']),
            ])
            ->sortBy('submitted_at')
            ->values();

        $peerReviews = $reviews->filter(
            fn (array $review): bool => $review['reviewer'] !== $author && ! $review['reviewer_is_bot'],
        );

        $firstReviewAt = $peerReviews->first()['submitted_at'] ?? null;
        $firstApprovalAt = $peerReviews->firstWhere('state', ReviewState::Approved)['submitted_at'] ?? null;

        $firstPeerCommentAt = $this->nodes($node, 'comments.nodes')
            ->reject(fn (array $comment): bool => data_get($comment, 'author.login') === $author || $this->isBot(data_get($comment, 'author')))
            ->map(fn (array $comment): CarbonImmutable => CarbonImmutable::parse($comment['createdAt']))
            ->sort()
            ->first();

        $firstResponseAt = collect([$firstReviewAt, $firstPeerCommentAt])->filter()->sort()->first();

        $attributes = [
            'title' => str($node['title'])->limit(250)->toString(),
            'url' => $node['url'],
            'state' => $node['state'],
            'is_draft' => $node['isDraft'],
            'author' => $author,
            'author_avatar_url' => data_get($node, 'author.avatarUrl'),
            'author_is_bot' => $this->isBot(data_get($node, 'author')),
            'base_ref' => $node['baseRefName'],
            'head_ref' => $node['headRefName'],
            'additions' => $node['additions'],
            'deletions' => $node['deletions'],
            'changed_files' => $node['changedFiles'],
            'commits_count' => data_get($node, 'commits.totalCount', 0),
            'comments_count' => data_get($node, 'comments.totalCount', 0),
            'review_comments_count' => $reviews->sum('comments_count'),
            'checks_state' => ChecksState::fromGitHub(data_get($node, 'lastCommit.nodes.0.commit.statusCheckRollup.state')),
            'labels' => $this->nodes($node, 'labels.nodes')->map(fn (array $label): array => Arr::only($label, ['name', 'color']))->all(),
            'first_commit_at' => $this->firstCommitAt($node, $openedAt, $repository->lookbackDays()),
            'opened_at' => $openedAt,
            'ready_at' => $this->readyAt($node, $openedAt, $firstResponseAt),
            'draft_intervals' => $this->draftIntervals($node, $openedAt),
            'first_response_at' => $firstResponseAt,
            'first_review_at' => $firstReviewAt,
            'first_approval_at' => $firstApprovalAt,
            'merged_at' => $node['mergedAt'] ? CarbonImmutable::parse($node['mergedAt']) : null,
            'closed_at' => $node['closedAt'] ? CarbonImmutable::parse($node['closedAt']) : null,
            'last_activity_at' => CarbonImmutable::parse($node['updatedAt']),
        ];

        $activities = $this->activities($node);

        DB::transaction(function () use ($repository, $node, $attributes, $reviews, $activities): void {
            $pullRequest = $repository->pullRequests()->updateOrCreate(['number' => $node['number']], $attributes);

            $pullRequest->reviews()->delete();
            $pullRequest->reviews()->createMany($reviews->all());

            $pullRequest->activities()->delete();
            $pullRequest->activities()->createMany($activities);
        });
    }

    /**
     * Commits, force pushes and conversation comments, used to work out whose turn it was.
     *
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function activities(array $node): array
    {
        $timeline = $this->nodes($node, 'activity.nodes')
            ->map(fn (array $item): ?array => match ($item['__typename'] ?? null) {
                'PullRequestCommit' => data_get($item, 'commit.committedDate') === null ? null : [
                    'kind' => PullRequestActivityKind::Commit,
                    'actor' => data_get($item, 'commit.author.user.login'),
                    'actor_is_bot' => false,
                    'occurred_at' => CarbonImmutable::parse(data_get($item, 'commit.committedDate')),
                ],
                'HeadRefForcePushedEvent' => [
                    'kind' => PullRequestActivityKind::ForcePush,
                    'actor' => data_get($item, 'actor.login'),
                    'actor_is_bot' => $this->isBot(data_get($item, 'actor')),
                    'occurred_at' => CarbonImmutable::parse($item['createdAt']),
                ],
                default => null,
            });

        $comments = $this->nodes($node, 'comments.nodes')->map(fn (array $comment): array => [
            'kind' => PullRequestActivityKind::Comment,
            'actor' => data_get($comment, 'author.login'),
            'actor_is_bot' => $this->isBot(data_get($comment, 'author')),
            'occurred_at' => CarbonImmutable::parse($comment['createdAt']),
        ]);

        return $timeline->merge($comments)->filter()->values()->all();
    }

    /**
     * Determine when the pull request became reviewable.
     *
     * Drafts only become reviewable once marked ready. A response that predates the ready
     * event means the pull request was reviewable from the start and later toggled to draft.
     *
     * @param  array<string, mixed>  $node
     */
    private function readyAt(array $node, CarbonImmutable $openedAt, ?CarbonImmutable $firstResponseAt): ?CarbonImmutable
    {
        $readyEvent = $this->nodes($node, 'draftEvents.nodes')
            ->where('__typename', 'ReadyForReviewEvent')
            ->pluck('createdAt')
            ->filter()
            ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date))
            ->sort()
            ->first();

        if ($readyEvent === null) {
            return $node['isDraft'] ? null : $openedAt;
        }

        if ($firstResponseAt !== null && $firstResponseAt->lt($readyEvent)) {
            return $openedAt;
        }

        return $readyEvent;
    }

    /**
     * When the pull request was a draft, as unix timestamps, from its ready for review and converted
     * to draft events. Without any, it has been whatever it is now since it was opened. A first event
     * that marks it ready means it was opened as a draft.
     *
     * @param  array<string, mixed>  $node
     * @return list<array{from: int, until: int|null}>
     */
    private function draftIntervals(array $node, CarbonImmutable $openedAt): array
    {
        $events = $this->nodes($node, 'draftEvents.nodes')
            ->filter(fn (array $event): bool => isset($event['createdAt']))
            ->map(fn (array $event): array => [
                'at' => CarbonImmutable::parse($event['createdAt'])->getTimestamp(),
                'draft' => $event['__typename'] === 'ConvertToDraftEvent',
            ])
            ->sortBy('at')
            ->values();

        if ($events->isEmpty()) {
            return $node['isDraft'] ? [['from' => $openedAt->getTimestamp(), 'until' => null]] : [];
        }

        $intervals = [];
        $draftSince = $events->first()['draft'] ? null : $openedAt->getTimestamp();

        foreach ($events as $event) {
            if ($event['draft'] && $draftSince === null) {
                $draftSince = $event['at'];
            } elseif (! $event['draft'] && $draftSince !== null) {
                $intervals[] = ['from' => $draftSince, 'until' => $event['at']];
                $draftSince = null;
            }
        }

        if ($draftSince !== null) {
            $intervals[] = ['from' => $draftSince, 'until' => null];
        }

        return $intervals;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function firstCommitAt(array $node, CarbonImmutable $openedAt, int $lookbackDays): ?CarbonImmutable
    {
        $date = data_get($node, 'firstCommit.nodes.0.commit.authoredDate')
            ?? data_get($node, 'firstCommit.nodes.0.commit.committedDate');

        if ($date === null) {
            return null;
        }

        $firstCommitAt = CarbonImmutable::parse($date);

        // Rebased or cherry-picked commits can carry dates far in the past; clamp to the lookback.
        return $firstCommitAt->max($openedAt->subDays($lookbackDays))->min($openedAt);
    }

    private function syncWorkflowRuns(Repository $repository, RepositorySync $state, CarbonImmutable $startedAt, CarbonImmutable $lookbackStart, bool $backfill): void
    {
        // Runs created in the last couple of days may still be running or get re-run, so overlap the window.
        $from = $backfill ? $lookbackStart : ($state->workflow_runs_synced_at?->subDays(2) ?? $lookbackStart);

        $state->update(['workflow_runs_from' => $from, 'workflow_runs_reached_at' => $from]);

        $this->fetchRunWindows($repository, $state, $this->runWindows($repository, $from, $startedAt));

        $state->update(['workflow_runs_synced_at' => $startedAt]);
    }

    /**
     * Split the window into windows within GitHub's search result limit, oldest first.
     *
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable, total: int}>
     */
    private function runWindows(Repository $repository, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $windows = [];
        $pending = [['from' => $from, 'to' => $to]];

        while ($pending !== []) {
            // A single run per page is enough to learn how many runs a window holds.
            $probes = $this->github->getMany($this->runsPath($repository), array_map(
                fn (array $window): array => ['per_page' => 1, 'page' => 1, 'created' => $this->runsCreated($window)],
                $pending,
            ), self::RestConcurrency);

            $next = [];

            foreach ($pending as $index => $window) {
                $total = (int) ($probes[$index]['total_count'] ?? 0);

                if ($total > self::RunSearchLimit && $window['from']->diffInMinutes($window['to']) > 60) {
                    array_push($next, ...$this->splitWindow($window['from'], $window['to'], (int) ceil($total / (self::RunSearchLimit * 0.9))));
                } else {
                    $windows[] = [...$window, 'total' => $total];
                }
            }

            $pending = $next;
        }

        usort($windows, fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $windows;
    }

    /**
     * Fetch the pages of all windows a batch at a time, oldest first. The watermark moves forward past
     * every window that is fully stored, so a sync that stops part way resumes where it left off instead
     * of fetching the whole lookback again.
     *
     * @param  list<array{from: CarbonImmutable, to: CarbonImmutable, total: int}>  $windows
     */
    private function fetchRunWindows(Repository $repository, RepositorySync $state, array $windows): void
    {
        $path = $this->runsPath($repository);
        $requests = [];
        $windowEnds = [];

        foreach ($windows as $window) {
            $total = min($window['total'], self::RunSearchLimit);

            for ($page = 1; $page <= (int) ceil($total / 100); $page++) {
                $requests[] = [
                    'query' => ['per_page' => 100, 'page' => $page, 'created' => $this->runsCreated($window)],
                    'expected' => min(100, $total - ($page - 1) * 100),
                ];
            }

            $windowEnds[] = count($requests);
        }

        $fetched = 0;
        $checkpoint = 0;

        while (true) {
            while ($checkpoint < count($windows) && $windowEnds[$checkpoint] <= $fetched) {
                $state->workflow_runs_synced_at = $state->workflow_runs_reached_at = $windows[$checkpoint]['to'];
                $checkpoint++;
            }

            $state->save();

            $batch = array_slice($requests, $fetched, 2 * self::RestConcurrency);

            if ($batch === []) {
                return;
            }

            foreach ($this->github->getMany($path, array_column($batch, 'query'), self::RestConcurrency) as $index => $page) {
                ['query' => $query, 'expected' => $planned] = $batch[$index];
                $runs = $this->runsOf($page);
                $expected = $this->expectedRuns($page, $query['page'], $planned);

                // GitHub's filtered run search occasionally serves short pages under load; never silently drop runs.
                if (count($runs) < $expected) {
                    $page = $this->github->get($path, $query);
                    $runs = $this->runsOf($page);
                    $expected = $this->expectedRuns($page, $query['page'], $planned);
                }

                if (count($runs) < $expected) {
                    throw new GitHubException('GitHub returned '.count($runs)." of {$expected} workflow runs for page {$query['page']} of {$query['created']}.");
                }

                $this->storeWorkflowRuns($repository, collect($runs));
                $state->workflow_runs_fetched += count($runs);
                $fetched++;
            }
        }
    }

    /**
     * How many runs a page should hold, going by the count GitHub reports alongside it. The window's
     * probe can count runs that are gone by the time the page is fetched, such as expired ones.
     *
     * @param  array<string, mixed>  $page
     */
    private function expectedRuns(array $page, int $number, int $planned): int
    {
        if (! isset($page['total_count'])) {
            return $planned;
        }

        $reported = min((int) $page['total_count'], self::RunSearchLimit) - ($number - 1) * 100;

        return max(0, min($planned, $reported));
    }

    private function runsPath(Repository $repository): string
    {
        return "repos/{$repository->owner}/{$repository->name}/actions/runs";
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $window
     */
    private function runsCreated(array $window): string
    {
        return $window['from']->toIso8601ZuluString().'..'.$window['to']->toIso8601ZuluString();
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<mixed>
     */
    private function runsOf(array $page): array
    {
        return is_array($page['workflow_runs'] ?? null) ? $page['workflow_runs'] : [];
    }

    /**
     * @param  Collection<array-key, mixed>  $runs
     */
    private function storeWorkflowRuns(Repository $repository, Collection $runs): void
    {
        if ($runs->isEmpty()) {
            return;
        }

        $rows = $runs->map(fn (array $run): array => $this->workflowRunRow($repository, $run));

        WorkflowRun::upsert($rows->all(), ['repository_id', 'github_id']);
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private function workflowRunRow(Repository $repository, array $run): array
    {
        $startedAt = isset($run['run_started_at']) ? CarbonImmutable::parse($run['run_started_at']) : null;
        $status = WorkflowRunStatus::fromGitHub($run['status'] ?? null);
        $completedAt = $status === WorkflowRunStatus::Completed ? CarbonImmutable::parse($run['updated_at']) : null;

        return [
            'repository_id' => $repository->id,
            'github_id' => $run['id'],
            'workflow_id' => $run['workflow_id'],
            'name' => str($run['name'] ?? 'Workflow')->limit(250)->toString(),
            'event' => $run['event'],
            'status' => $status->value,
            'conclusion' => WorkflowRunConclusion::fromGitHub($run['conclusion'] ?? null)?->value,
            'head_branch' => $run['head_branch'],
            'head_sha' => $run['head_sha'],
            'run_attempt' => $run['run_attempt'] ?? 1,
            'pull_request_number' => data_get($run, 'pull_requests.0.number'),
            'url' => $run['html_url'],
            'run_started_at' => $startedAt,
            'completed_at' => $completedAt,
            'duration_seconds' => WorkflowRun::durationBetween($startedAt, $completedAt),
            // A re-run restarts the clock on the same run, so only first attempts tell us how long jobs queued.
            'queue_seconds' => ($run['run_attempt'] ?? 1) === 1 ? WorkflowRun::durationBetween(CarbonImmutable::parse($run['created_at']), $startedAt) : null,
            'created_at' => CarbonImmutable::parse($run['created_at']),
        ];
    }

    /**
     * The nodes of a GraphQL connection nested in the given payload. Connections can hold null for
     * items GitHub no longer returns, such as deleted ones, so those are left out.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array<mixed>>
     */
    private function nodes(array $payload, string $path): Collection
    {
        $nodes = data_get($payload, $path);

        return collect(is_array($nodes) ? array_values(array_filter($nodes, is_array(...))) : []);
    }

    /**
     * @param  array<string, mixed>|null  $actor
     */
    private function isBot(?array $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return ($actor['__typename'] ?? null) === 'Bot' || str_ends_with($actor['login'] ?? '', '[bot]');
    }
}
