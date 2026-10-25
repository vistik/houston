<?php

namespace Database\Seeders;

use App\Enums\ChecksState;
use App\Enums\DeploymentSource;
use App\Enums\DeploymentState;
use App\Enums\PullRequestActivityKind;
use App\Enums\PullRequestState;
use App\Enums\RepositorySyncStatus;
use App\Enums\ReviewState;
use App\Enums\WorkflowRunConclusion;
use App\Enums\WorkflowRunStatus;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TimelineEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A year of made-up history for three repositories and two teams, for demos. Three timeline
 * events change how the teams work, and the data follows: introducing AI code reviews 60 days ago
 * clearly speeds everything up, so its before and after comparison has a story to tell.
 *
 * Run it on a fresh database: `php artisan migrate:fresh --seeder=DemoSeeder`. The repositories don't
 * exist on GitHub, so syncing them fails.
 */
class DemoSeeder extends Seeder
{
    /**
     * How many days of history to make up, which is also each repository's lookback, so the stretch
     * before every event is covered.
     */
    private const int Days = 365;

    private const string Ai = 'ai';

    private const string ClaudeTeam = 'claude';

    private const string SlackBot = 'slack';

    /**
     * When each event happened, in days ago, and what it's called.
     *
     * @var array<string, array{daysAgo: int, title: string}>
     */
    private const array Events = [
        self::ClaudeTeam => ['daysAgo' => 150, 'title' => 'Switched to Claude Team'],
        self::Ai => ['daysAgo' => 60, 'title' => 'Introduced AI code reviews'],
        self::SlackBot => ['daysAgo' => 21, 'title' => 'Deployed Slack bot for GitHub review reminders'],
    ];

    /**
     * Each team and its members.
     *
     * @var array<string, list<string>>
     */
    private const array Teams = [
        'Platform' => ['alice', 'bob', 'carol', 'dan'],
        'Product' => ['erin', 'frank', 'grace', 'heidi'],
    ];

    /**
     * Each repository, the team that mostly works on it, and how many pull requests it gets on a
     * working day before any of the events.
     *
     * @var array<string, array{team: string, perDay: float, description: string, private: bool}>
     */
    private const array Repositories = [
        'api' => ['team' => 'Platform', 'perDay' => 3.2, 'description' => 'The public API and background workers', 'private' => true],
        'web' => ['team' => 'Product', 'perDay' => 2.6, 'description' => 'The customer-facing web app', 'private' => true],
        'mobile' => ['team' => 'Product', 'perDay' => 1.4, 'description' => 'iOS and Android apps', 'private' => false],
    ];

    private const array Subjects = [
        'billing webhooks', 'invoice export', 'search ranking', 'onboarding flow', 'rate limiter', 'audit log',
        'team invitations', 'password reset', 'dark mode', 'push notifications', 'CSV import', 'usage dashboard',
        'API pagination', 'feature flags', 'session handling', 'image uploads', 'email digests', 'SSO login',
        'checkout page', 'retry queue', 'cache warming', 'permissions model', 'settings page', 'activity feed',
    ];

    private const array Verbs = ['Add', 'Fix', 'Refactor', 'Speed up', 'Simplify', 'Improve', 'Clean up', 'Support'];

    private CarbonImmutable $now;

    private int $githubId = 1;

    private int $deploymentId = 1;

    public function run(): void
    {
        // The same history on every run, so demos and screenshots line up.
        mt_srand(2026);
        $this->now = CarbonImmutable::now();

        DB::transaction(function (): void {
            foreach (self::Events as $event) {
                TimelineEvent::create(['title' => $event['title'], 'happened_on' => $this->now->subDays($event['daysAgo'])->toDateString()]);
            }

            foreach (self::Teams as $name => $members) {
                $team = Team::create(['name' => $name, 'slug' => Team::uniqueSlug($name)]);

                foreach ($members as $login) {
                    // Heidi joined later, so the team report has a membership that starts part way through.
                    $team->memberships()->create(['login' => $login, 'joined_on' => $login === 'heidi' ? $this->now->subDays(120)->toDateString() : null]);
                }
            }

            foreach (self::Repositories as $name => $repository) {
                $this->seedRepository($name, $repository);
            }
        });
    }

    /**
     * @param  array{team: string, perDay: float, description: string, private: bool}  $settings
     */
    private function seedRepository(string $name, array $settings): void
    {
        $repository = Repository::create(['owner' => 'acme', 'name' => $name, 'lookback_days' => self::Days]);
        $repository->sync()->create([
            'status' => RepositorySyncStatus::Succeeded,
            'default_branch' => 'main',
            'description' => $settings['description'],
            'is_private' => $settings['private'],
            'repository_created_at' => $this->now->subYears(3),
            'history_from' => $this->now->subDays(self::Days)->startOfDay(),
            'deploy_source' => DeploymentSource::Deployments->value,
            'started_at' => $this->now->subMinutes(2),
            'pull_requests_synced_at' => $this->now,
            'workflow_runs_synced_at' => $this->now,
            'deployments_synced_at' => $this->now,
            'finished_at' => $this->now,
        ]);

        $authors = self::Teams[$settings['team']];
        $others = array_values(array_diff(array_merge(...array_values(self::Teams)), $authors));
        $number = 1;
        $merged = [];

        for ($daysAgo = self::Days; $daysAgo >= 0; $daysAgo--) {
            $day = $this->now->subDays($daysAgo)->startOfDay();
            $weekend = $day->isWeekend();
            $count = $this->poisson($settings['perDay'] * $this->effect($day, 'throughput') * ($weekend ? 0.08 : 1));

            for ($i = 0; $i < $count; $i++) {
                $openedAt = $day->addMinutes(mt_rand(8 * 60, 18 * 60));

                if ($openedAt->gt($this->now)) {
                    continue;
                }

                // Mostly the owning team, sometimes someone from the other one helps out.
                $author = mt_rand(1, 100) <= 85 ? $this->pick($authors) : $this->pick($others);
                $pullRequest = $this->seedPullRequest($repository, $number++, $author, $authors, $openedAt);

                if ($pullRequest !== null) {
                    $merged[] = $pullRequest;
                }
            }

            if (! $weekend && $daysAgo % 7 === 2) {
                $this->seedBotPullRequest($repository, $number++, $day->addHours(6));
            }
        }

        $this->seedDeployments($repository, $merged, $authors, $number);
    }

    /**
     * Plays out a pull request from first commit to merge: pickup, review rounds with rework in
     * between, approval and merge, with CI on every push. Returns when it merged and its head commit,
     * or null while it's still open or when it was closed.
     *
     * @param  list<string>  $team
     * @return array{mergedAt: CarbonImmutable, sha: string}|null
     */
    private function seedPullRequest(Repository $repository, int $number, string $author, array $team, CarbonImmutable $openedAt, ?string $title = null, bool $quick = false): ?array
    {
        $reviewers = array_values(array_diff($team, [$author]));
        $reviewer = $this->pick($reviewers);
        $aiReviews = $openedAt->gte($this->eventDay(self::Ai));
        $draft = ! $quick && mt_rand(1, 100) <= 12;
        $readyAt = $draft ? $this->addWorkingHours($openedAt, $this->jitter(5)) : $openedAt;
        $firstCommitAt = $this->subWorkingHours($openedAt, $this->jitter($quick ? 1 : 22 * $this->effect($openedAt, 'coding'), 0.7));

        $reviews = [];
        $activities = [[PullRequestActivityKind::Commit, $author, $firstCommitAt]];
        $pushes = [$readyAt];

        if ($aiReviews) {
            // The AI reviewer comments within minutes. It's a bot, so it doesn't count as the human response.
            $reviews[] = ['reviewer' => 'acme-ai-reviewer[bot]', 'bot' => true, 'state' => ReviewState::Commented, 'comments' => mt_rand(1, 6), 'at' => $readyAt->addMinutes(mt_rand(3, 9))];
        }

        $at = $this->addWorkingHours($readyAt, $this->jitter($quick ? 0.5 : 9 * $this->effect($readyAt, 'pickup'), 0.8));
        $firstResponseAt = $at;
        $changesRequested = $quick ? 0.0 : 0.55 * $this->effect($readyAt, 'changes');
        $rounds = 0;

        while (true) {
            if ($rounds < 4 && mt_rand(1, 1000) <= $changesRequested * 1000) {
                $reviews[] = ['reviewer' => $reviewer, 'bot' => false, 'state' => ReviewState::ChangesRequested, 'comments' => mt_rand(2, 8), 'at' => $at];
                $activities[] = [PullRequestActivityKind::Comment, $reviewer, $at->addMinutes(2)];
                $at = $this->addWorkingHours($at, $this->jitter(7 * $this->effect($at, 'rework'), 0.7));
                $activities[] = [PullRequestActivityKind::Commit, $author, $at];
                $pushes[] = $at;
                $at = $this->addWorkingHours($at, $this->jitter(8 * $this->effect($at, 'turnaround'), 0.8));
                $changesRequested *= 0.5;
                $rounds++;

                continue;
            }

            $reviews[] = ['reviewer' => $reviewer, 'bot' => false, 'state' => ReviewState::Approved, 'comments' => mt_rand(0, 2), 'at' => $at];
            break;
        }

        $approvedAt = $at;
        $mergedAt = $this->addWorkingHours($approvedAt, $this->jitter($quick ? 0.3 : 4 * $this->effect($approvedAt, 'merge'), 0.9));
        $closedWithoutMerge = ! $quick && mt_rand(1, 100) <= 6;
        $open = $mergedAt->gt($this->now);
        $visible = fn (CarbonImmutable $moment): bool => $moment->lte($this->now);
        $sha = sha1("{$repository->id}:{$number}");

        $id = DB::table('pull_requests')->insertGetId([
            'repository_id' => $repository->id,
            'number' => $number,
            'title' => $title ?? $this->pick(self::Verbs).' '.$this->pick(self::Subjects),
            'url' => "https://github.com/{$repository->fullName()}/pull/{$number}",
            'state' => ($open ? PullRequestState::Open : ($closedWithoutMerge ? PullRequestState::Closed : PullRequestState::Merged))->value,
            'is_draft' => $draft && $readyAt->gt($this->now),
            'author' => $author,
            'author_is_bot' => false,
            'base_ref' => 'main',
            'head_ref' => "{$author}/".substr($sha, 0, 7),
            // AI reviews nudge people towards smaller pull requests.
            'additions' => (int) round($this->jitter($quick ? 12 : 140 * $this->effect($openedAt, 'size'), 0.9)),
            'deletions' => (int) round($this->jitter($quick ? 8 : 45 * $this->effect($openedAt, 'size'), 1)),
            'changed_files' => mt_rand(1, $quick ? 2 : 14),
            'commits_count' => count($pushes) + mt_rand(0, 3),
            'comments_count' => $rounds * 2 + mt_rand(0, 3),
            'review_comments_count' => array_sum(array_column(array_filter($reviews, fn (array $review): bool => ! $review['bot']), 'comments')),
            'checks_state' => ChecksState::Success->value,
            'first_commit_at' => $firstCommitAt,
            'opened_at' => $openedAt,
            'ready_at' => $visible($readyAt) ? $readyAt : null,
            'draft_intervals' => $draft ? json_encode([['from' => $openedAt->getTimestamp(), 'until' => $visible($readyAt) ? $readyAt->getTimestamp() : null]]) : null,
            'first_response_at' => $visible($firstResponseAt) ? $firstResponseAt : null,
            'first_review_at' => $visible($firstResponseAt) ? $firstResponseAt : null,
            'first_approval_at' => $visible($approvedAt) ? $approvedAt : null,
            'merged_at' => $open || $closedWithoutMerge ? null : $mergedAt,
            'closed_at' => $open ? null : $mergedAt,
            'last_activity_at' => min($this->now, $mergedAt),
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        DB::table('pull_request_reviews')->insert(array_map(fn (array $review): array => [
            'pull_request_id' => $id,
            'reviewer' => $review['reviewer'],
            'reviewer_is_bot' => $review['bot'],
            'state' => $review['state']->value,
            'comments_count' => $review['comments'],
            'submitted_at' => $review['at'],
        ], array_values(array_filter($reviews, fn (array $review): bool => $visible($review['at'])))));

        DB::table('pull_request_activities')->insert(array_map(fn (array $activity): array => [
            'pull_request_id' => $id,
            'kind' => $activity[0]->value,
            'actor' => $activity[1],
            'actor_is_bot' => false,
            'occurred_at' => $activity[2],
        ], array_values(array_filter($activities, fn (array $activity): bool => $visible($activity[2])))));

        foreach ($pushes as $index => $pushedAt) {
            if ($visible($pushedAt)) {
                $this->seedRun($repository, 'pull_request', "{$author}/".substr($sha, 0, 7), sha1("{$sha}:{$index}"), $pushedAt, $number);
            }
        }

        if ($open || $closedWithoutMerge) {
            return null;
        }

        $this->seedRun($repository, 'push', 'main', $sha, $mergedAt->addMinute());

        return ['mergedAt' => $mergedAt, 'sha' => $sha];
    }

    private function seedBotPullRequest(Repository $repository, int $number, CarbonImmutable $openedAt): void
    {
        $mergedAt = $openedAt->addHours(mt_rand(2, 72));
        $merged = $mergedAt->lte($this->now);

        DB::table('pull_requests')->insert([
            'repository_id' => $repository->id,
            'number' => $number,
            'title' => 'Bump '.$this->pick(['laravel/framework', 'vite', 'react', 'phpunit/phpunit', 'tailwindcss']).' to the latest version',
            'url' => "https://github.com/{$repository->fullName()}/pull/{$number}",
            'state' => ($merged ? PullRequestState::Merged : PullRequestState::Open)->value,
            'is_draft' => false,
            'author' => 'dependabot[bot]',
            'author_is_bot' => true,
            'base_ref' => 'main',
            'head_ref' => "dependabot/{$number}",
            'additions' => mt_rand(2, 40),
            'deletions' => mt_rand(2, 40),
            'changed_files' => mt_rand(1, 2),
            'commits_count' => 1,
            'opened_at' => $openedAt,
            'ready_at' => $openedAt,
            'first_commit_at' => $openedAt,
            'merged_at' => $merged ? $mergedAt : null,
            'closed_at' => $merged ? $mergedAt : null,
            'last_activity_at' => min($this->now, $mergedAt),
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    /**
     * Ships to production late every working afternoon that has something new. Some releases break
     * something; a hotfix pull request follows and goes out in an extra release a few hours later.
     *
     * @param  list<array{mergedAt: CarbonImmutable, sha: string}>  $merged
     * @param  list<string>  $team
     */
    private function seedDeployments(Repository $repository, array $merged, array $team, int $number): void
    {
        usort($merged, fn (array $a, array $b): int => $a['mergedAt'] <=> $b['mergedAt']);
        $last = $this->now->subDays(self::Days + 1);

        for ($daysAgo = self::Days; $daysAgo >= 0; $daysAgo--) {
            $day = $this->now->subDays($daysAgo)->startOfDay();
            $deployedAt = $day->addMinutes(mt_rand(16 * 60, 17 * 60 + 30));

            if ($day->isWeekend() || $deployedAt->gt($this->now)) {
                continue;
            }

            $shipped = array_values(array_filter($merged, fn (array $pullRequest): bool => $pullRequest['mergedAt']->gt($last) && $pullRequest['mergedAt']->lte($deployedAt)));

            if ($shipped === []) {
                continue;
            }

            $this->deploy($repository, end($shipped)['sha'], $shipped[0]['mergedAt'], $deployedAt);
            $last = $deployedAt;

            if (mt_rand(1, 1000) > 140 * $this->effect($deployedAt, 'failures')) {
                continue;
            }

            // The release broke something: a quick hotfix, merged and shipped the same evening.
            $fix = $this->seedPullRequest($repository, $number++, $this->pick($team), $team, $deployedAt->addMinutes(mt_rand(40, 120)), title: $this->pick(['Hotfix: ', 'Revert "']).$this->pick(self::Subjects), quick: true);

            if ($fix !== null && $fix['mergedAt']->addMinutes(20)->lte($this->now)) {
                $this->deploy($repository, $fix['sha'], $fix['mergedAt'], $fix['mergedAt']->addMinutes(20));
                $last = $fix['mergedAt']->addMinutes(20);
            }
        }
    }

    private function deploy(Repository $repository, string $sha, CarbonImmutable $committedAt, CarbonImmutable $deployedAt): void
    {
        DB::table('deployments')->insert([
            'repository_id' => $repository->id,
            'source' => DeploymentSource::Deployments->value,
            'external_id' => (string) $this->deploymentId++,
            'environment' => 'production',
            'ref' => 'main',
            'sha' => $sha,
            'state' => DeploymentState::Success->value,
            'url' => "https://github.com/{$repository->fullName()}/deployments",
            'committed_at' => $committedAt,
            'deployed_at' => $deployedAt,
        ]);
    }

    /**
     * A tests and a lint run for a push. Tests sometimes fail; a fair share of those pass when re-run
     * on the same commit, so the flaky and re-run rates have something to show.
     */
    private function seedRun(Repository $repository, string $event, string $branch, string $sha, CarbonImmutable $at, ?int $pullRequest = null): void
    {
        foreach ([1 => 'tests', 2 => 'lint'] as $workflowId => $name) {
            $failed = mt_rand(1, 1000) <= ($workflowId === 1 ? 170 : 40) * $this->effect($at, 'ci');
            $minutes = $workflowId === 1 ? $this->jitter(9, 0.25) : $this->jitter(2, 0.3);
            $this->insertRun($repository, $workflowId, $name, $event, $branch, $sha, $at, $minutes, $failed, 1, $pullRequest);

            if ($failed && mt_rand(1, 100) <= 45) {
                $this->insertRun($repository, $workflowId, $name, $event, $branch, $sha, $at->addMinutes((int) $minutes + mt_rand(5, 30)), $minutes, false, 2, $pullRequest);
            }
        }
    }

    private function insertRun(Repository $repository, int $workflowId, string $name, string $event, string $branch, string $sha, CarbonImmutable $at, float $minutes, bool $failed, int $attempt, ?int $pullRequest): void
    {
        $queue = mt_rand(5, 60);
        $startedAt = $at->addSeconds($queue);
        $completedAt = $startedAt->addSeconds((int) round($minutes * 60));
        $done = $completedAt->lte($this->now);

        if ($at->gt($this->now)) {
            return;
        }

        $id = $this->githubId++;

        DB::table('workflow_runs')->insert([
            'repository_id' => $repository->id,
            'github_id' => $id,
            'workflow_id' => $workflowId,
            'name' => $name,
            'event' => $event,
            'status' => ($done ? WorkflowRunStatus::Completed : WorkflowRunStatus::InProgress)->value,
            'conclusion' => $done ? ($failed ? WorkflowRunConclusion::Failure : WorkflowRunConclusion::Success)->value : null,
            'head_branch' => $branch,
            'head_sha' => $sha,
            'run_attempt' => $attempt,
            'pull_request_number' => $pullRequest,
            'url' => "https://github.com/{$repository->fullName()}/actions/runs/{$id}",
            'run_started_at' => $startedAt,
            'completed_at' => $done ? $completedAt : null,
            'duration_seconds' => $done ? (int) round($minutes * 60) : null,
            'queue_seconds' => $queue,
            'created_at' => $at,
        ]);
    }

    /**
     * How much each event changes an aspect of how people work at a moment, as a multiplier. Each
     * change phases in over the week after its event, as people get used to it.
     */
    private function effect(CarbonImmutable $at, string $aspect): float
    {
        $impact = [
            self::ClaudeTeam => ['throughput' => 1.12, 'coding' => 0.7],
            self::Ai => ['throughput' => 1.6, 'coding' => 0.85, 'pickup' => 0.75, 'changes' => 0.35, 'rework' => 0.55, 'turnaround' => 0.5, 'merge' => 0.6, 'ci' => 0.4, 'failures' => 0.3, 'size' => 0.7],
            self::SlackBot => ['throughput' => 1.1, 'pickup' => 0.4, 'turnaround' => 0.7, 'merge' => 0.7],
        ];
        $multiplier = 1.0;

        foreach ($impact as $event => $aspects) {
            $adoption = max(0.0, min(1.0, $this->eventDay($event)->diffInHours($at, false) / (7 * 24)));
            $multiplier *= 1 + (($aspects[$aspect] ?? 1.0) - 1) * $adoption;
        }

        return $multiplier;
    }

    private function eventDay(string $event): CarbonImmutable
    {
        return $this->now->subDays(self::Events[$event]['daysAgo'])->startOfDay();
    }

    /**
     * Moves forward by working hours, skipping weekends as the default working time does.
     */
    private function addWorkingHours(CarbonImmutable $from, float $hours): CarbonImmutable
    {
        $at = $from;
        $minutes = (int) round($hours * 60);

        while ($minutes > 0) {
            if ($at->isWeekend()) {
                $at = $at->addDay()->startOfDay();

                continue;
            }

            $step = min($minutes, (int) ceil($at->diffInMinutes($at->addDay()->startOfDay())));
            $at = $at->addMinutes($step);
            $minutes -= $step;
        }

        return $at;
    }

    private function subWorkingHours(CarbonImmutable $from, float $hours): CarbonImmutable
    {
        $at = $from;
        $minutes = (int) round($hours * 60);

        while ($minutes > 0) {
            $previous = $at->subMinute();

            if ($previous->isWeekend()) {
                $at = $previous->startOfDay();

                continue;
            }

            $step = min($minutes, (int) ceil($at->startOfDay()->diffInMinutes($at)) ?: 24 * 60);
            $at = $at->subMinutes($step);
            $minutes -= $step;
        }

        return $at;
    }

    /**
     * A value that lands around `$median`, skewed the way durations are: mostly close, now and then
     * much longer.
     */
    private function jitter(float $median, float $spread = 0.6): float
    {
        $gaussian = sqrt(-2 * log(max(1e-9, mt_rand() / mt_getrandmax()))) * cos(2 * M_PI * mt_rand() / mt_getrandmax());

        return max(0.05, $median * exp($spread * $gaussian));
    }

    private function poisson(float $mean): int
    {
        $limit = exp(-$mean);
        $count = 0;

        for ($product = mt_rand() / mt_getrandmax(); $product > $limit; $product *= mt_rand() / mt_getrandmax()) {
            $count++;
        }

        return $count;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $values
     * @return T
     */
    private function pick(array $values): mixed
    {
        return $values[array_rand($values)];
    }
}
