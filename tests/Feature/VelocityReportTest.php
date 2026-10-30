<?php

use App\Enums\PullRequestActivityKind;
use App\Enums\PullRequestState;
use App\Enums\ReviewState;
use App\Enums\WorkflowRunConclusion;
use App\Metrics\BranchStatus;
use App\Metrics\CiFailureRateScope;
use App\Metrics\OpenPullRequestStatus;
use App\Metrics\Period;
use App\Models\Deployment;
use App\Models\PullRequest;
use App\Models\PullRequestActivity;
use App\Models\PullRequestReview;
use App\Models\Repository;
use App\Models\WorkflowJob;
use App\Models\WorkflowRun;
use App\Reports\VelocityReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->synced()->create();
    $this->now = CarbonImmutable::now();
});

function velocityReport(Repository $repository, int $days = 7, bool $includeBots = false): array
{
    return (new VelocityReport($repository->fresh('sync'), Period::lastDays($days, 'UTC'), $includeBots))->toArray();
}

it('computes median time to merge, pickup, review and approval for the period', function () {
    $opened = $this->now->subDays(3);

    PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 2, approvedAfter: 4, mergedAfter: 10)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 4, approvedAfter: 6, mergedAfter: 20)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 6, approvedAfter: 8, mergedAfter: 30)->create();

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['throughput']['value'])->toBe(3)
        ->and($kpis['cycleTime']['value'])->toEqual(20 * 3600)
        ->and($kpis['pickupTime']['value'])->toEqual(4 * 3600)
        ->and($kpis['reviewTime']['value'])->toEqual(16 * 3600)
        ->and($kpis['timeToApproval']['value'])->toEqual(6 * 3600)
        ->and($kpis['mergeDelay']['value'])->toEqual(14 * 3600)
        ->and($kpis['codingTime']['value'])->toEqual(3600)
        ->and($kpis['leadTime']['value'])->toEqual(21 * 3600)
        ->and($kpis['unreviewedMergeRate']['value'])->toEqual(0);
});

it('reports both the median and the average next to the headline statistic', function () {
    $opened = $this->now->subDays(3);

    PullRequest::factory()->for($this->repository)->lifecycle($opened, mergedAfter: 10)->create(['review_comments_count' => 0, 'comments_count' => 1]);
    PullRequest::factory()->for($this->repository)->lifecycle($opened, mergedAfter: 20)->create(['review_comments_count' => 1, 'comments_count' => 1]);
    PullRequest::factory()->for($this->repository)->lifecycle($opened, mergedAfter: 60)->create(['review_comments_count' => 3, 'comments_count' => 3]);

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['cycleTime'])->toMatchArray(['statistic' => 'median', 'value' => 20 * 3600.0, 'median' => 20 * 3600.0, 'average' => 30 * 3600.0])
        ->and($kpis['reviewDepth'])->toMatchArray(['statistic' => 'average', 'value' => 3.0, 'median' => 2.0, 'average' => 3.0])
        ->and($kpis['reviewDepth']['value'])->toBe($kpis['reviewDepth']['average'])
        ->and($kpis['throughput'])->not->toHaveKeys(['statistic', 'median', 'average']);
});

it('compares against the previous period', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(10), mergedAfter: 5)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(11), mergedAfter: 5)->create();

    $throughput = velocityReport($this->repository)['kpis']['throughput'];

    expect($throughput['value'])->toBe(1)->and($throughput['previous'])->toBe(2);
});

it('excludes bot pull requests unless asked to include them', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDay(), mergedAfter: 1)->create();
    PullRequest::factory()->for($this->repository)->bot()->lifecycle($this->now->subDay(), mergedAfter: 1)->create();

    expect(velocityReport($this->repository)['kpis']['throughput']['value'])->toBe(1)
        ->and(velocityReport($this->repository, includeBots: true)['kpis']['throughput']['value'])->toBe(2);
});

it('computes merge rate and unreviewed merges', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), respondedAfter: 1, mergedAfter: 2)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 2)->create();
    PullRequest::factory()->for($this->repository)->create([
        'state' => PullRequestState::Closed,
        'opened_at' => $this->now->subDays(3),
        'closed_at' => $this->now->subDay(),
    ]);

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['mergeRate']['value'])->toEqual(2 / 3)
        ->and($kpis['unreviewedMergeRate']['value'])->toEqual(0.5);
});

it('classifies open pull requests by what they are waiting on', function () {
    $opened = $this->now->subDays(2);

    $awaiting = PullRequest::factory()->for($this->repository)->lifecycle($opened)->create();
    $draft = PullRequest::factory()->for($this->repository)->lifecycle($opened)->create(['is_draft' => true]);
    $changes = PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 1)->create();
    $approved = PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 1, approvedAfter: 2)->create();
    $stale = PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(30))->create();

    PullRequestReview::create(['pull_request_id' => $changes->id, 'reviewer' => 'bob', 'state' => ReviewState::ChangesRequested, 'submitted_at' => $opened->addHour()]);
    PullRequestReview::create(['pull_request_id' => $approved->id, 'reviewer' => 'bob', 'state' => ReviewState::ChangesRequested, 'submitted_at' => $opened->addHour()]);
    PullRequestReview::create(['pull_request_id' => $approved->id, 'reviewer' => 'bob', 'state' => ReviewState::Approved, 'submitted_at' => $opened->addHours(2)]);

    $statuses = collect(velocityReport($this->repository)['workInProgress'])->pluck('status', 'number');

    expect($statuses[$awaiting->number])->toBe(OpenPullRequestStatus::AwaitingReview)
        ->and($statuses[$draft->number])->toBe(OpenPullRequestStatus::Draft)
        ->and($statuses[$changes->number])->toBe(OpenPullRequestStatus::WaitingOnAuthor)
        ->and($statuses[$approved->number])->toBe(OpenPullRequestStatus::Approved)
        ->and(velocityReport($this->repository)['kpis']['stalePullRequests']['value'])->toBe(1)
        ->and(velocityReport($this->repository)['workInProgress'][0]['number'])->toBe($stale->number);
});

it('computes CI failure rates, durations and recovery time', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    $factory->at($this->now->subDays(3), 10)->create();
    $factory->failed()->at($this->now->subDays(2), 4)->create();
    $factory->at($this->now->subDays(2)->addHours(3), 6)->create();
    $factory->failed()->at($this->now->subDay(), 8)->create(['event' => 'pull_request', 'head_branch' => 'feature']);
    $factory->at($this->now->subDay(), 5)->create(['conclusion' => WorkflowRunConclusion::Cancelled]);
    $factory->at($this->now->subDays(3), 12)->create(['run_attempt' => 2]);

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciRuns']['value'])->toBe(6)
        ->and($kpis['ciFailureRate']['value'])->toEqual(1 / 4)
        ->and($kpis['ciDefaultBranchFailureRate']['value'])->toEqual(1 / 4)
        ->and($kpis['ciPullRequestFailureRate']['value'])->toEqual(1.0)
        ->and($kpis['ciDuration']['value'])->toEqual(8 * 60)
        ->and($kpis['ciRerunRate']['value'])->toEqual(1 / 5)
        ->and($kpis['ciRecoveryTime']['value'])->toEqual(3 * 3600 + 6 * 60);
});

it('counts only runs on the default branch in the CI failure rate unless told to count every branch', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    $factory->at($this->now->subDays(2))->create();
    $factory->failed()->at($this->now->subDays(2))->create(['head_branch' => 'release/1.x']);
    $factory->failed()->at($this->now->subDay())->create(['event' => 'pull_request', 'head_branch' => 'feature']);
    $factory->failed()->at($this->now->subDay())->create(['event' => 'pull_request', 'head_branch' => 'main']);

    $report = fn (CiFailureRateScope $scope): array => (new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'UTC'), ciFailureRateScope: $scope))->toArray();
    $defaultBranch = $report(CiFailureRateScope::DefaultBranch);
    $allBranches = $report(CiFailureRateScope::AllBranches);
    $charted = fn (array $report): array => collect($report['trend'])->pluck('ciFailureRate')->filter(fn (?float $rate): bool => $rate !== null)->values()->all();

    expect($defaultBranch['ciFailureRateScope'])->toBe(CiFailureRateScope::DefaultBranch)
        ->and($defaultBranch['kpis']['ciFailureRate']['value'])->toEqual(0)
        ->and($charted($defaultBranch))->toBe([0.0])
        ->and(array_sum(array_column($defaultBranch['trend'], 'ciRuns')))->toBe(4)
        ->and($allBranches['kpis']['ciFailureRate']['value'])->toEqual(3 / 4)
        ->and($charted($allBranches))->toBe([0.5, 1.0])
        ->and($allBranches['kpis']['ciDefaultBranchFailureRate']['value'])->toEqual(0);
});

it('measures CI duration per commit by its slowest workflow', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    // Quick automation workflows on the same commit must not drag the duration down.
    foreach (['abc', 'def'] as $sha) {
        $factory->at($this->now->subDays(2), 10)->create(['head_sha' => $sha, 'workflow_id' => 1, 'name' => 'CI']);
        $factory->at($this->now->subDays(2))->create(['head_sha' => $sha, 'workflow_id' => 2, 'name' => 'Label', 'duration_seconds' => 8]);
        $factory->at($this->now->subDays(2))->create(['head_sha' => $sha, 'workflow_id' => 3, 'name' => 'Linear', 'duration_seconds' => 10]);
    }

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciDuration']['value'])->toEqual(10 * 60)
        ->and($kpis['ciDurationP90']['value'])->toEqual(10 * 60);
});

it('ignores runs that expired while waiting for approval', function () {
    WorkflowRun::factory()->for($this->repository)->failed()->at($this->now->subDays(5), 60 * 24 * 30)->create();
    WorkflowRun::factory()->for($this->repository)->at($this->now->subDays(2))->create();

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciFailureRate']['value'])->toEqual(0)
        ->and($kpis['ciDuration']['value'])->toEqual(300);
});

it('summarises the overview with the same numbers as the full report', function () {
    $factory = WorkflowRun::factory()->for($this->repository);
    $factory->at($this->now->subDays(10))->create(['workflow_id' => 1]);
    $factory->failed()->at($this->now->subDays(3))->create(['workflow_id' => 1]);
    $factory->failed()->at($this->now->subDays(2), 60 * 24 * 30)->create(['workflow_id' => 2]);
    $factory->at($this->now->subHours(2))->create(['workflow_id' => 2]);
    $factory->failed()->at($this->now->subDay())->create(['workflow_id' => 3, 'event' => 'pull_request', 'head_branch' => 'feature']);
    $factory->at($this->now->subHours(5))->create(['workflow_id' => 3, 'event' => 'pull_request', 'head_branch' => 'feature', 'conclusion' => WorkflowRunConclusion::Cancelled]);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(3), respondedAfter: 2, approvedAfter: 4, mergedAfter: 10)->create();

    $summary = (new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'UTC')))->summary();
    $full = velocityReport($this->repository);

    expect($summary['kpis'])->toBe(collect($full['kpis'])->only(VelocityReport::SummaryKpis)->all())
        ->and($summary['kpis']['ciFailureRate']['value'])->toEqual(1 / 2)
        ->and($summary['defaultBranchStatus'])->toBe($full['defaultBranchStatus'])
        ->and($summary['trend'])->toBe(array_map(fn (array $point): array => ['date' => $point['date'], 'merged' => $point['merged']], $full['trend']))
        ->and($summary['series'])->toBe(collect($full['series'])->only(VelocityReport::SummaryKpis)->all())
        ->and($summary['series'])->toHaveKeys(['throughput', 'cycleTime', 'pickupTime', 'openPullRequests', 'deployments'])
        ->and($summary['period'])->toBe(collect($full['period'])->only(['to', 'bucketDays', 'granularity'])->all());
});

it('computes CI metrics in the database instead of loading every run', function () {
    WorkflowRun::factory()->for($this->repository)->count(3)->at($this->now->subDays(2))->create();

    DB::enableQueryLog();
    velocityReport($this->repository);

    $loadedRuns = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql): bool => str_starts_with($sql, 'select * from "workflow_runs"'));

    expect($loadedRuns)->toBeEmpty();
});

it('loads reviews and activities in bulk, however many pull requests there are', function () {
    $create = function (int $count): void {
        foreach (range(1, $count) as $offset) {
            $opened = $this->now->subDays($offset);
            $pr = PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 2, approvedAfter: 4, mergedAfter: 10)->create();
            PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => 'bob', 'state' => ReviewState::Approved, 'submitted_at' => $opened->addHours(4)]);
            PullRequestActivity::create(['pull_request_id' => $pr->id, 'kind' => PullRequestActivityKind::Commit, 'actor' => 'alice', 'occurred_at' => $opened->addHours(3)]);
        }
    };
    $queries = function (string $method): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        (new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'UTC')))->{$method}();

        return count(DB::getQueryLog());
    };

    $create(2);
    $few = ['toArray' => $queries('toArray'), 'wall' => $queries('wall'), 'summary' => $queries('summary')];
    $create(4);

    expect(['toArray' => $queries('toArray'), 'wall' => $queries('wall'), 'summary' => $queries('summary')])->toBe($few);
});

it('only loads timelines for open pull requests on the overview', function () {
    $merged = PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), respondedAfter: 2, mergedAfter: 10)->create();
    $open = PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'opened_at' => $this->now->subDays(9), 'ready_at' => $this->now->subDays(9)]);

    DB::enableQueryLog();
    $summary = (new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'UTC')))->summary();
    $timelineQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "pull_request_activities"'));

    expect($summary['kpis']['stalePullRequests']['value'])->toBe(1)
        ->and($timelineQueries)->toHaveCount(1)
        // Eager loads inline integer keys into the query instead of binding them.
        ->and(Str::between($timelineQueries->first()['query'], 'in (', ')'))->toBe((string) $open->id)
        ->and($merged->state)->toBe(PullRequestState::Merged);
});

it('breaks the trend into daily buckets for short periods and weekly buckets for long ones', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create();

    $daily = velocityReport($this->repository, 7);
    $weekly = velocityReport($this->repository, 90);

    expect($daily['trend'])->toHaveCount(7)
        ->and(collect($daily['trend'])->sum('merged'))->toBe(1)
        ->and($weekly['trend'])->toHaveCount(13)
        ->and($weekly['period']['bucketDays'])->toBe(7);
});

it('charts each day from midnight in the timezone reports are shown in', function () {
    config(['houston.working_time.days' => [1, 2, 3, 4, 5]]);

    // Friday 22:00 in New York, which is already Saturday in UTC.
    $fridayEvening = CarbonImmutable::parse('2026-10-03 02:00:00');
    PullRequest::factory()->for($this->repository)->lifecycle($fridayEvening->subHours(2), mergedAfter: 2)->create();
    WorkflowRun::factory()->for($this->repository)->at($fridayEvening)->create();

    $merged = fn (array $trend): array => array_column($trend, 'merged', 'date');
    $utc = velocityReport($this->repository);

    $newYork = (new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'America/New_York')))->toArray();

    expect($merged($utc['trend']))->toMatchArray(['2026-10-02' => 0, '2026-10-03' => 1])
        ->and($merged($newYork['trend']))->toMatchArray(['2026-10-02' => 1, '2026-10-03' => 0])
        ->and(array_column($newYork['trend'], 'ciRuns', 'date'))->toMatchArray(['2026-10-02' => 1, '2026-10-03' => 0])
        ->and(array_column($newYork['trend'], 'date'))->toBe(['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07'])
        ->and($newYork['period']['to'])->toBe('2026-10-07T08:00:00-04:00')
        ->and($newYork['workingTime'])->toBe('Mon–Fri (America/New_York)');
});

it('sorts CI runs into days on the local clock across a daylight saving change', function () {
    CarbonImmutable::setTestNow('2026-11-02 12:00:00');

    // Copenhagen goes from UTC+2 to UTC+1 on October 25: 23:30 and 00:10 local on either side of
    // midnight on October 27, more than four times 24 hours after the period starts.
    WorkflowRun::factory()->for($this->repository)->at(CarbonImmutable::parse('2026-10-26 22:30:00'))->create();
    WorkflowRun::factory()->for($this->repository)->at(CarbonImmutable::parse('2026-10-26 23:10:00'))->create();

    $period = Period::between(CarbonImmutable::parse('2026-10-22'), CarbonImmutable::parse('2026-10-28'), 'Europe/Copenhagen');
    $trend = (new VelocityReport($this->repository->fresh('sync'), $period))->toArray()['trend'];

    expect(array_column($trend, 'ciRuns', 'date'))->toMatchArray(['2026-10-26' => 1, '2026-10-27' => 1]);
});

it('charts everything the headline numbers count, from midnight on the first day through now', function () {
    $firstDay = CarbonImmutable::parse('2026-10-01 00:00:00');

    // Just before the period, just after it starts, and today.
    PullRequest::factory()->for($this->repository)->lifecycle($firstDay->subHours(3), mergedAfter: 2)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($firstDay->addMinutes(30), mergedAfter: 1)->create(['base_ref' => 'main']);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subHours(2), mergedAfter: 1)->create();
    // A deployment counts by what it shipped since the one before.
    Deployment::factory()->for($this->repository)->at($firstDay->subHours(5))->create();
    Deployment::factory()->for($this->repository)->at($firstDay->addHours(3))->create();
    WorkflowRun::factory()->for($this->repository)->at($firstDay->addHours(4))->create();

    $report = velocityReport($this->repository);

    expect(array_column($report['trend'], 'date'))->toHaveCount(7)
        ->and($report['trend'][0]['date'])->toBe('2026-10-01')
        ->and(array_sum(array_column($report['trend'], 'merged')))->toBe($report['kpis']['throughput']['value'])->toBe(2)
        ->and(array_sum(array_column($report['trend'], 'opened')))->toBe($report['kpis']['opened']['value'])
        ->and(array_sum(array_column($report['trend'], 'deployments')))->toBe($report['kpis']['deployments']['value'])->toBe(1)
        ->and(array_sum(array_column($report['trend'], 'ciRuns')))->toBe(1)
        ->and(array_sum($report['series']['throughput']))->toBe(2);
});

it('counts the pull requests open at the end of each trend bucket, ready for review and drafts', function () {
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'opened_at' => $this->now->subDays(20), 'ready_at' => $this->now->subDays(20)]);
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'opened_at' => $this->now->subHours(36), 'ready_at' => $this->now->subHours(36)]);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(10), mergedAfter: 156)->create();
    PullRequest::factory()->for($this->repository)->create([
        'state' => PullRequestState::Closed,
        'opened_at' => $this->now->subDays(8),
        'ready_at' => $this->now->subDays(8),
        'closed_at' => $this->now->subHours(132),
    ]);
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'is_draft' => false, 'opened_at' => $this->now->subDays(6), 'ready_at' => $this->now->subHours(54)]);
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'is_draft' => true, 'opened_at' => $this->now->subDays(6), 'ready_at' => null]);
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'is_draft' => true, 'opened_at' => $this->now->subDays(6), 'ready_at' => $this->now->subDays(5)]);

    $trend = velocityReport($this->repository)['trend'];

    expect(array_column($trend, 'open'))->toBe([2, 2, 1, 1, 3, 3, 3])
        ->and(array_column($trend, 'openDrafts'))->toBe([3, 2, 2, 2, 1, 1, 1]);
});

it('charts pull requests that went back to draft from when they were drafts', function () {
    $at = fn (string $moment): int => CarbonImmutable::parse($moment)->getTimestamp();

    PullRequest::factory()->for($this->repository)->create([
        'state' => PullRequestState::Open, 'is_draft' => false, 'merged_at' => null, 'closed_at' => null,
        'opened_at' => '2026-09-30 13:00:00', 'ready_at' => '2026-09-30 13:00:00',
        'draft_intervals' => [['from' => $at('2026-10-02 00:00:00'), 'until' => $at('2026-10-04 00:00:00')]],
    ]);
    PullRequest::factory()->for($this->repository)->create([
        'state' => PullRequestState::Closed, 'is_draft' => true, 'merged_at' => null, 'closed_at' => '2026-10-05 00:00:00',
        'opened_at' => '2026-10-01 00:00:00', 'ready_at' => null,
        'draft_intervals' => [['from' => $at('2026-10-01 00:00:00'), 'until' => null]],
    ]);

    $trend = velocityReport($this->repository)['trend'];

    expect(array_column($trend, 'open'))->toBe([0, 0, 1, 1, 1, 1, 1])
        ->and(array_column($trend, 'openDrafts'))->toBe([2, 2, 1, 0, 0, 0, 0]);
});

it('breaks every pull request metric into the trend buckets', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(5), respondedAfter: 2, mergedAfter: 4)->create();
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create();

    $report = velocityReport($this->repository);
    $series = $report['series'];

    expect($series['throughput'])->toHaveCount(count($report['trend']))
        ->and(array_sum($series['throughput']))->toBe(2)
        ->and(array_sum($series['opened']))->toBe(2)
        ->and(array_filter($series['unreviewedMergeRate'], fn ($rate) => $rate !== null))->toEqual([1 => 0, 4 => 1])
        ->and($series)->toHaveKeys(['waitingOnReviewer', 'reviewRounds', 'reviewConcentration', 'pullRequestSize', 'mergeRate'])
        ->and($series)->not->toHaveKey('ciFailureRate');
});

it('breaks delivery metrics and open pull requests into the trend buckets, but not CI metrics', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create();
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(5), mergedAfter: 2)->create(['base_ref' => 'main']);
    Deployment::factory()->for($this->repository)->at($this->now->subDays(4))->create();
    PullRequest::factory()->for($this->repository)->create(['state' => PullRequestState::Open, 'opened_at' => $this->now->subDays(3), 'ready_at' => $this->now->subDays(3)]);

    $report = velocityReport($this->repository);
    $series = $report['series'];

    expect($series['deployments'])->toBe(array_column($report['trend'], 'deployments'))
        ->and(array_sum($series['deployments']))->toBe(1)
        ->and($series['changeFailureRate'][3])->toEqual(0)
        ->and($series['changeFailureRate'][0])->toBeNull()
        ->and($series['openPullRequests'])->toBe(array_map(fn (array $point): int => $point['open'] + $point['openDrafts'], $report['trend']))
        ->and(end($series['openPullRequests']))->toBe($report['kpis']['openPullRequests']['value'])
        ->and($series)->not->toHaveKeys(['ciFailureRate', 'ciDuration', 'stalePullRequests']);
});

it('counts the people who opened, merged or reviewed pull requests as active developers', function () {
    $opened = $this->now->subDays(3);

    $pr = PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 2, mergedAfter: 4)->create(['author' => 'alice']);
    PullRequest::factory()->for($this->repository)->create(['author' => 'alice', 'opened_at' => $opened->addHour()]);
    PullRequest::factory()->for($this->repository)->create(['author' => 'carol', 'opened_at' => $opened->addHour()]);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(10), mergedAfter: 2)->create(['author' => 'dave']);
    PullRequest::factory()->for($this->repository)->bot()->lifecycle($opened, mergedAfter: 1)->create();

    PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => 'bob', 'state' => ReviewState::Approved, 'submitted_at' => $opened->addHours(2)]);
    PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => 'alice', 'state' => ReviewState::Commented, 'submitted_at' => $opened->addHours(3)]);

    $report = velocityReport($this->repository);

    expect($report['kpis']['activeDevelopers']['value'])->toBe(3)
        ->and($report['kpis']['activeDevelopers']['previous'])->toBe(1)
        ->and(velocityReport($this->repository, includeBots: true)['kpis']['activeDevelopers']['value'])->toBe(3)
        ->and(array_sum($report['series']['activeDevelopers']))->toBe(3);
});

it('reports whether the default branch is currently green', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    expect(velocityReport($this->repository)['defaultBranchStatus']['state'])->toBe(BranchStatus::Unknown);

    $factory->at($this->now->subHours(5))->create(['workflow_id' => 1, 'name' => 'tests']);
    $factory->failed()->at($this->now->subHours(3))->create(['workflow_id' => 1, 'name' => 'tests']);
    $factory->failed()->at($this->now->subHours(2))->create(['workflow_id' => 1, 'name' => 'tests']);
    $factory->at($this->now->subHour())->create(['workflow_id' => 2, 'name' => 'lint']);
    $factory->failed()->at($this->now->subMinutes(10))->create(['workflow_id' => 3, 'name' => 'pr checks', 'event' => 'pull_request', 'head_branch' => 'feature']);

    $status = velocityReport($this->repository)['defaultBranchStatus'];

    expect($status['state'])->toBe(BranchStatus::Failing)
        ->and($status['failingWorkflows'])->toBe(['tests'])
        ->and($status['brokenSince'])->toBe($this->now->subHours(3)->toIso8601String());

    $factory->at($this->now->subMinutes(5))->create(['workflow_id' => 1, 'name' => 'tests']);

    expect(velocityReport($this->repository)['defaultBranchStatus']['state'])->toBe(BranchStatus::Passing);
});

it('measures people in working time and machines in wall-clock time', function () {
    config(['houston.working_time.days' => [1, 2, 3, 4, 5]]);

    // Opened Friday 2026-10-02 12:00, merged Monday 2026-10-05 12:00: 3 calendar days, 1 working day.
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-10-02 12:00'), mergedAfter: 72)->create();
    WorkflowRun::factory()->for($this->repository)->at(CarbonImmutable::parse('2026-10-03 10:00'), 30)->create();

    $report = velocityReport($this->repository);

    expect($report['kpis']['cycleTime']['value'])->toEqual(86400)
        ->and($report['kpis']['ciDuration']['value'])->toEqual(1800)
        ->and($report['workingTime'])->toBe('Mon–Fri (UTC)');
});

it('splits review time into waiting on reviewers and waiting on authors', function () {
    $opened = $this->now->subDays(3);
    $pr = PullRequest::factory()->for($this->repository)->lifecycle($opened, respondedAfter: 2, approvedAfter: 8, mergedAfter: 9)->create(['author' => 'alice']);

    PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => 'bob', 'state' => ReviewState::ChangesRequested, 'submitted_at' => $opened->addHours(2)]);
    PullRequestActivity::create(['pull_request_id' => $pr->id, 'kind' => PullRequestActivityKind::Commit, 'actor' => 'alice', 'occurred_at' => $opened->addHours(6)]);
    PullRequestReview::create(['pull_request_id' => $pr->id, 'reviewer' => 'bob', 'state' => ReviewState::Approved, 'submitted_at' => $opened->addHours(8)]);

    $report = velocityReport($this->repository);

    expect($report['kpis']['waitingOnReviewer']['value'])->toEqual(4 * 3600)
        ->and($report['kpis']['waitingOnAuthor']['value'])->toEqual(4 * 3600)
        ->and($report['kpis']['reviewRounds']['value'])->toEqual(1.0)
        ->and($report['kpis']['reworkRate']['value'])->toEqual(1.0)
        ->and(collect($report['cycleBreakdown'])->pluck('seconds', 'stage')->only(['pickup', 'author', 'review', 'merge'])->all())
        ->toEqual(['pickup' => 7200, 'author' => 14400, 'review' => 7200, 'merge' => 3600]);
});

it('only keeps the streaks that broke and the latest one of each workflow, however many runs passed', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    foreach (range(1, 30) as $hour) {
        $factory->at($this->now->subDays(5)->addHours($hour))->create(['workflow_id' => 1]);
        $factory->at($this->now->subDays(2)->addHours($hour))->create(['workflow_id' => 1]);
        $factory->at($this->now->subDays(5)->addHours($hour))->create(['workflow_id' => 2, 'name' => 'lint']);
    }

    $factory->failed()->at($this->now->subDays(3))->create(['workflow_id' => 1]);
    $factory->at($this->now->subDays(3)->addHours(2))->create(['workflow_id' => 1]);

    $report = new VelocityReport($this->repository->fresh('sync'), Period::lastDays(7, 'UTC'));
    $kpis = $report->toArray()['kpis'];
    $streaks = (fn () => $this->streaks)->call((fn () => $this->ci)->call($report));

    expect($streaks)->toHaveCount(3)
        ->and($streaks->where('workflow_id', 1)->first()['broken_at']->toIso8601String())->toBe($this->now->subDays(3)->toIso8601String())
        ->and($kpis['ciRecoveryTime']['value'])->toEqual(2 * 3600 + 5 * 60);
});

it('measures CI queue time, flakiness and how long the default branch was red', function () {
    $factory = WorkflowRun::factory()->for($this->repository);

    // Commit abc failed then passed: flaky. Commit def passed on its second attempt: flaky.
    $factory->failed()->at($this->now->subDays(3))->create(['head_sha' => 'abc', 'queue_seconds' => 60]);
    $factory->at($this->now->subDays(3)->addHours(6))->create(['head_sha' => 'abc', 'queue_seconds' => 120]);
    $factory->at($this->now->subDays(2))->create(['head_sha' => 'def', 'run_attempt' => 2, 'queue_seconds' => null]);
    $factory->at($this->now->subDay())->create(['head_sha' => 'ghi', 'queue_seconds' => 30]);

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciQueueTime']['value'])->toEqual(60)
        ->and($kpis['ciFlakyRate']['value'])->toEqual(2 / 3)
        // The period runs from midnight six days ago until noon today.
        ->and($kpis['defaultBranchRedShare']['value'])->toEqual((6 * 3600 + 300) / (6.5 * 86400));
});

it('ranks the jobs that failed and then passed on a re-run by how often, with what they cost', function () {
    $factory = WorkflowRun::factory()->for($this->repository);
    $job = function (WorkflowRun $run, string $name, int $attempt, string $conclusion, int $minutes = 5, int $doneAfter = 0, ?string $step = null): void {
        static $id = 1;

        WorkflowJob::create([
            'workflow_run_id' => $run->id, 'github_id' => $id, 'name' => $name, 'run_attempt' => $attempt, 'conclusion' => $conclusion,
            'url' => "https://github.com/acme/app/actions/runs/{$run->github_id}/job/{$id}", 'duration_seconds' => $minutes * 60,
            'completed_at' => $run->created_at->addMinutes($doneAfter), 'failed_step' => $step,
        ]);

        $id++;
    };

    // "browser" failed twice before passing on the third attempt, 50 minutes after it first failed.
    $first = $factory->at(CarbonImmutable::parse('2026-10-05 10:00'))->create(['run_attempt' => 3, 'jobs_attempt' => 3]);
    $job($first, 'browser', 1, 'failure', 10, 10, 'Run browser tests');
    $job($first, 'browser', 2, 'timed_out', 12, 40, 'Run actions/checkout@v4');
    $job($first, 'browser', 3, 'success', doneAfter: 60);
    $job($first, 'unit', 1, 'success', doneAfter: 5);

    // Both were flaky in the same run, which kept whoever pushed waiting from 09:03 until 09:35.
    $second = $factory->at(CarbonImmutable::parse('2026-10-06 09:00'))->create(['run_attempt' => 2, 'jobs_attempt' => 2]);
    $job($second, 'browser', 1, 'failure', 8, 8, 'Run browser tests');
    $job($second, 'browser', 2, 'success', doneAfter: 30);
    $job($second, 'unit', 1, 'failure', 3, 3, 'Run tests');
    $job($second, 'unit', 2, 'success', doneAfter: 35);

    // Failing for good isn't flaky, and neither is a job cancelled when another failed.
    $broken = $factory->failed()->at($this->now->subHours(3))->create(['run_attempt' => 2, 'jobs_attempt' => 2]);
    $job($broken, 'lint', 1, 'failure');
    $job($broken, 'lint', 2, 'failure');
    $job($broken, 'unit', 1, 'cancelled');
    $job($broken, 'unit', 2, 'success');

    // The week before, "browser" was flaky once and took 20 minutes to get past.
    $before = $factory->at(CarbonImmutable::parse('2026-09-28 10:00'))->create(['run_attempt' => 2, 'jobs_attempt' => 2]);
    $job($before, 'browser', 1, 'failure', 6, 6, 'Run browser tests');
    $job($before, 'browser', 2, 'success', doneAfter: 26);

    // A re-run whose jobs haven't been fetched yet, and an excluded workflow.
    $factory->at($this->now->subHours(3))->create(['run_attempt' => 2]);
    $label = $factory->at($this->now->subHours(3))->create(['workflow_id' => 9, 'run_attempt' => 2, 'jobs_attempt' => 2]);
    $job($label, 'label', 1, 'failure');
    $job($label, 'label', 2, 'success');
    $this->repository->update(['excluded_workflow_ids' => [9]]);

    $flaky = velocityReport($this->repository)['flakyJobs'];

    expect($flaky['jobs'])->toBe([
        [
            'workflowId' => 1, 'workflow' => 'tests', 'name' => 'browser', 'runs' => 2, 'previousRuns' => 1, 'failures' => 3, 'seconds' => 30 * 60, 'waitSeconds' => 72 * 60,
            'steps' => [
                ['name' => 'Run browser tests', 'failures' => 2, 'setup' => false],
                ['name' => 'Run actions/checkout@v4', 'failures' => 1, 'setup' => true],
            ],
            'url' => "https://github.com/acme/app/actions/runs/{$second->github_id}/job/5",
        ],
        [
            'workflowId' => 1, 'workflow' => 'tests', 'name' => 'unit', 'runs' => 1, 'previousRuns' => 0, 'failures' => 1, 'seconds' => 3 * 60, 'waitSeconds' => 32 * 60,
            'steps' => [['name' => 'Run tests', 'failures' => 1, 'setup' => false]],
            'url' => "https://github.com/acme/app/actions/runs/{$second->github_id}/job/7",
        ],
    ])
        ->and($flaky['count'])->toBe(2)
        ->and($flaky['seconds'])->toBe(33 * 60)
        ->and($flaky['waitSeconds'])->toBe((50 + 32) * 60)
        ->and($flaky['pendingRuns'])->toBe(1);
});

it('compares the cost of flaky jobs with the period before', function () {
    $run = WorkflowRun::factory()->for($this->repository);
    $flaky = function (CarbonImmutable $at, int $minutes, int $waited) use ($run): void {
        $rerun = $run->at($at)->create(['run_attempt' => 2, 'jobs_attempt' => 2]);

        WorkflowJob::create(['workflow_run_id' => $rerun->id, 'github_id' => $rerun->id * 10, 'name' => 'browser', 'run_attempt' => 1, 'conclusion' => 'failure', 'url' => 'https://github.com', 'duration_seconds' => $minutes * 60, 'completed_at' => $at]);
        WorkflowJob::create(['workflow_run_id' => $rerun->id, 'github_id' => $rerun->id * 10 + 1, 'name' => 'browser', 'run_attempt' => 2, 'conclusion' => 'success', 'url' => 'https://github.com', 'completed_at' => $at->addMinutes($waited)]);
    };

    $flaky(CarbonImmutable::parse('2026-10-06 10:00'), minutes: 10, waited: 45);
    $flaky(CarbonImmutable::parse('2026-09-29 10:00'), minutes: 4, waited: 15);
    $flaky(CarbonImmutable::parse('2026-09-30 10:00'), minutes: 6, waited: 15);

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciFlakyJobWait'])->toMatchArray(['value' => 45 * 60, 'previous' => 30 * 60, 'unit' => 'duration', 'better' => 'lower'])
        ->and($kpis['ciFlakyJobMinutes'])->toMatchArray(['value' => 10, 'previous' => 10, 'unit' => 'count']);
});

it('leaves the cost of flaky jobs unknown until a re-run has its jobs fetched', function () {
    WorkflowRun::factory()->for($this->repository)->at($this->now->subDay())->create(['run_attempt' => 2]);
    WorkflowRun::factory()->for($this->repository)->at($this->now->subDays(9))->create();

    $kpis = velocityReport($this->repository)['kpis'];

    expect($kpis['ciFlakyJobWait']['value'])->toBeNull()
        ->and($kpis['ciFlakyJobMinutes']['value'])->toBeNull()
        // Without any re-runs, nothing was flaky.
        ->and($kpis['ciFlakyJobWait']['previous'])->toBe(0);
});

it('tells GitHub setup steps from the steps a workflow runs itself', function (string $step, bool $setup) {
    expect(WorkflowJob::isSetupStep($step))->toBe($setup);
})->with([
    ['Set up job', true],
    ['Initialize containers', true],
    ['Run actions/checkout@v4', true],
    ['Post Run actions/cache@v4', true],
    ['Run shivammathur/setup-php@v2', true],
    ['Run pnpm/action-setup@v4', true],
    ['Run cypress-io/github-action@v6', false],
    ['Run browser tests', false],
    ['Checkout', false],
]);

it('flags reverts and hotfixes and tracks the bot backlog separately', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create(['title' => 'Revert "Add feature"']);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create(['head_ref' => 'hotfix/login']);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create();
    PullRequest::factory()->for($this->repository)->bot()->lifecycle($this->now->subDays(10))->create();

    $report = velocityReport($this->repository);

    expect($report['kpis']['remediations']['value'])->toBe(2)
        ->and($report['remediations'])->toHaveCount(2)
        ->and($report['botBacklog']['open'])->toBe(1)
        ->and($report['botBacklog']['oldestSeconds'])->toBe(10 * 86400);
});

it('only counts human activity when deciding a pull request is stale', function () {
    $pr = PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(30))->create(['last_activity_at' => $this->now->subHour()]);
    PullRequestActivity::create(['pull_request_id' => $pr->id, 'kind' => PullRequestActivityKind::Comment, 'actor' => 'vercel[bot]', 'actor_is_bot' => true, 'occurred_at' => $this->now->subHour()]);

    $fresh = PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(30))->create();
    PullRequestActivity::create(['pull_request_id' => $fresh->id, 'kind' => PullRequestActivityKind::Commit, 'actor' => 'alice', 'occurred_at' => $this->now->subDay()]);

    $report = velocityReport($this->repository);
    $stale = collect($report['workInProgress'])->pluck('isStale', 'number');

    expect($stale[$pr->number])->toBeTrue()
        ->and($stale[$fresh->number])->toBeFalse()
        ->and($report['kpis']['stalePullRequests']['value'])->toBe(1);
});
