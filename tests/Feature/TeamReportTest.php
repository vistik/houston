<?php

use App\Enums\PullRequestState;
use App\Metrics\Period;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Reports\VelocityReport;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->now = CarbonImmutable::now();
});

function teamReport(Team $team): array
{
    return (new VelocityReport($team->fresh(), Period::lastDays(7, 'UTC')))->toArray();
}

it('reports the pull requests its members authored across every repository', function () {
    $team = Team::factory()->create();
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);
    $api = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
    $web = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'web']);

    PullRequest::factory()->for($api)->lifecycle($this->now->subDays(3), mergedAfter: 5)->create(['author' => 'alice']);
    PullRequest::factory()->for($web)->lifecycle($this->now->subDays(3), mergedAfter: 10)->create(['author' => 'Alice']);
    PullRequest::factory()->for($api)->lifecycle($this->now->subDays(3), mergedAfter: 5)->create(['author' => 'bob']);

    $report = teamReport($team);

    expect($report['kpis']['throughput']['value'])->toBe(2)
        ->and(array_column($report['slowestMerged'], 'repository'))->toBe(['acme/web', 'acme/api']);
});

it('counts what happens to a pull request for the team its author was on at the time', function () {
    $platform = Team::factory()->create();
    $payments = Team::factory()->create();
    TeamMembership::factory()->for($platform)->create(['login' => 'alice', 'left_on' => '2026-10-04']);
    TeamMembership::factory()->for($payments)->create(['login' => 'alice', 'joined_on' => '2026-10-04']);

    PullRequest::factory()->lifecycle(CarbonImmutable::parse('2026-10-02 10:00'), respondedAfter: 2, mergedAfter: 72)->create(['author' => 'alice']);

    $before = teamReport($platform)['kpis'];
    $after = teamReport($payments)['kpis'];

    expect([$before['opened']['value'], $before['pickupTime']['value'], $before['throughput']['value']])->toEqual([1, 2 * 3600, 0])
        ->and([$after['opened']['value'], $after['pickupTime']['value'], $after['throughput']['value']])->toEqual([0, null, 1]);
});

it('counts open pull requests for the team their author is on at each point in time', function () {
    $platform = Team::factory()->create();
    $payments = Team::factory()->create();
    TeamMembership::factory()->for($platform)->create(['login' => 'alice', 'left_on' => '2026-10-04']);
    TeamMembership::factory()->for($payments)->create(['login' => 'alice', 'joined_on' => '2026-10-04']);

    PullRequest::factory()->create(['author' => 'alice', 'state' => PullRequestState::Open, 'opened_at' => '2026-09-25 10:00', 'ready_at' => '2026-09-25 10:00', 'draft_intervals' => []]);

    $before = teamReport($platform);
    $after = teamReport($payments);

    expect(array_column($before['trend'], 'open'))->toBe([1, 1, 0, 0, 0, 0, 0])
        ->and(array_column($after['trend'], 'open'))->toBe([0, 0, 1, 1, 1, 1, 1])
        ->and($before['kpis']['openPullRequests']['value'])->toBe(0)
        ->and($before['workInProgress'])->toBeEmpty()
        ->and($after['kpis']['openPullRequests']['value'])->toBe(1)
        ->and($after['workInProgress'])->toHaveCount(1);
});

it('leaves out CI and deployments, which belong to repositories rather than teams', function () {
    $team = Team::factory()->create();
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);

    $report = teamReport($team);

    expect($report)->not->toHaveKeys(['workflows', 'defaultBranchStatus', 'delivery', 'botBacklog'])
        ->and($report['kpis'])->toHaveKeys(['throughput', 'cycleTime', 'openPullRequests'])
        ->and($report['kpis'])->not->toHaveKeys(['ciFailureRate', 'deployments', 'changeFailureRate'])
        ->and($report['trend'][0])->not->toHaveKeys(['ciRuns', 'ciFailureRate', 'deployments']);
});
