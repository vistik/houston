<?php

use App\Enums\ReviewState;
use App\Mcp\Prompts\GiveFeedback;
use App\Mcp\Servers\HoustonServer;
use App\Mcp\Tools\GetVelocityReport;
use App\Mcp\Tools\ListPullRequests;
use App\Mcp\Tools\ListRepositories;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    $this->now = CarbonImmutable::now();
});

it('lists watched repositories with their headline KPIs', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), respondedAfter: 1, mergedAfter: 3)->create();

    HoustonServer::tool(ListRepositories::class, ['days' => 7])
        ->assertOk()
        ->assertSee(['"days":7', '"fullName":"acme/app"', '"throughput":{"value":1', '"isStale":false']);

    Queue::assertNothingPushed();
});

it('returns the full velocity report for a repository', function () {
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), respondedAfter: 1, mergedAfter: 3)->create();
    PullRequest::factory()->for($this->repository)->bot()->lifecycle($this->now->subDays(2), mergedAfter: 1)->create();

    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'days' => 14])
        ->assertOk()
        ->assertSee(['"fullName":"acme/app"', '"days":14', '"cycleBreakdown"', '"delivery"', '"throughput":{"value":1']);

    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'include_bots' => true])
        ->assertSee('"throughput":{"value":2');
});

it('explains which repositories exist when asked for an unknown one', function () {
    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/missing'])
        ->assertHasErrors(['Houston does not watch [acme/missing]. Watched repositories: acme/app.']);
});

it('rejects reporting windows the dashboards do not offer', function () {
    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'days' => 3])
        ->assertHasErrors();
});

it('reports on a custom date range', function () {
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-09-08 10:00'), respondedAfter: 1, mergedAfter: 3)->create();
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-10-02 10:00'), respondedAfter: 1, mergedAfter: 3)->create();

    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'from' => '2026-09-01', 'to' => '2026-09-15'])
        ->assertOk()
        ->assertSee(['"days":15', '"custom":true', '"from":"2026-09-01T00:00:00+00:00"', '"throughput":{"value":1']);

    HoustonServer::tool(ListRepositories::class, ['from' => '2026-09-01', 'to' => '2026-09-15'])
        ->assertSee(['"days":15', '"from":"2026-09-01"', '"to":"2026-09-15"', '"throughput":{"value":1']);
});

it('lists only pull requests that finished within a custom date range', function () {
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-09-08 10:00'), mergedAfter: 3)->create(['number' => 1]);
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-09-15 20:00'), mergedAfter: 1)->create(['number' => 2]);
    PullRequest::factory()->for($this->repository)->lifecycle(CarbonImmutable::parse('2026-09-16 10:00'), mergedAfter: 1)->create(['number' => 3]);

    HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'from' => '2026-09-01', 'to' => '2026-09-15'])
        ->assertOk()
        ->assertSee(['"from":"2026-09-01"', '"to":"2026-09-15"', '"total":2', '"number":1,', '"number":2,'])
        ->assertDontSee('"number":3,');
});

it('rejects incomplete or malformed date ranges', function (array $range) {
    HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', ...$range])
        ->assertHasErrors();
})->with([
    'from without to' => [['from' => '2026-09-01']],
    'to without from' => [['to' => '2026-09-15']],
    'not a date' => [['from' => 'last week', 'to' => '2026-09-15']],
]);

it('lists pull requests with their reviews', function () {
    $merged = PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), respondedAfter: 1, approvedAfter: 2, mergedAfter: 3)
        ->create(['number' => 42, 'author' => 'taylor', 'additions' => 120, 'deletions' => 30]);
    PullRequestReview::create(['pull_request_id' => $merged->id, 'reviewer' => 'nuno', 'state' => ReviewState::Approved, 'comments_count' => 2, 'submitted_at' => $this->now->subDays(2)->addHours(2)]);
    PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(40), mergedAfter: 3)->create(['number' => 7]);
    PullRequest::factory()->for($this->repository)->create(['number' => 99, 'author' => 'taylor']);
    PullRequest::factory()->for($this->repository)->bot()->lifecycle($this->now->subDay(), mergedAfter: 1)->create(['number' => 100]);

    HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'days' => 30])
        ->assertOk()
        ->assertSee(['"total":1', '"number":42', '"additions":120', '"reviewer":"nuno"', '"state":"APPROVED"'])
        ->assertDontSee(['"number":7,', '"number":99,', '"number":100,']);

    HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'state' => 'all', 'author' => 'taylor'])
        ->assertSee(['"total":2', '"number":42', '"number":99']);

    HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'include_bots' => true, 'limit' => 1])
        ->assertSee(['"total":2', '"number":100'])
        ->assertDontSee('"number":42,');
});

it('offers a prompt that guides the agent through giving feedback', function () {
    HoustonServer::prompt(GiveFeedback::class, ['repository' => 'acme/app', 'days' => 14, 'focus' => 'code review'])
        ->assertOk()
        ->assertSee(['the repository acme/app', 'last 14 days', 'get-velocity-report', 'list-pull-requests', 'Pay particular attention to: code review.']);

    HoustonServer::prompt(GiveFeedback::class, ['repository' => 'acme/app', 'from' => '2026-09-01', 'to' => '2026-09-15'])
        ->assertSee(['from 2026-09-01 through 2026-09-15', 'get-velocity-report (from: 2026-09-01, to: 2026-09-15)']);
});

it('only serves MCP over HTTP to clients with the configured token', function () {
    $initialize = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1.0']]];

    $this->postJson('/mcp', $initialize)->assertNotFound();

    config(['houston.mcp_token' => 'secret']);

    $this->postJson('/mcp', $initialize)->assertUnauthorized();
    $this->postJson('/mcp', $initialize, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    $this->postJson('/mcp', $initialize, ['Authorization' => 'Bearer secret', 'Accept' => 'application/json, text/event-stream'])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Houston');
});
