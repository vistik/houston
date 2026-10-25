<?php

use App\Enums\PullRequestState;
use App\Mcp\Prompts\GiveFeedback;
use App\Mcp\Servers\HoustonServer;
use App\Mcp\Tools\GetVelocityReport;
use App\Mcp\Tools\ListPullRequests;
use App\Models\PullRequest;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->now = CarbonImmutable::now();
});

/**
 * The pull request numbers a list-pull-requests call returned, in order.
 *
 * @param  array<string, mixed>  $arguments
 * @return list<int>
 */
function listedNumbers(array $arguments): array
{
    config(['houston.mcp_token' => 'secret']);

    $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-pull-requests', 'arguments' => ['repository' => 'acme/app', ...$arguments]]];
    $response = test()->postJson('/mcp', $call, ['Authorization' => 'Bearer secret', 'Accept' => 'application/json, text/event-stream'])
        ->assertOk()
        ->assertJsonPath('result.isError', false);

    return collect(json_decode($response->json('result.content.0.text'), true)['pullRequests'])->pluck('number')->all();
}

describe('repository lookup', function () {
    it('says no repositories are watched when asked for one before any were added', function () {
        HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app'])
            ->assertHasErrors(['Houston does not watch [acme/app]. Watched repositories: none.']);
    });

    it('lists every watched repository alphabetically when the name does not match', function () {
        Repository::factory()->create(['owner' => 'zeta', 'name' => 'api']);
        Repository::factory()->create(['owner' => 'acme', 'name' => 'web']);
        Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        HoustonServer::tool(ListPullRequests::class, ['repository' => 'app'])
            ->assertHasErrors(['Houston does not watch [app]. Watched repositories: acme/app, acme/web, zeta/api.']);
    });

    it('requires a repository', function () {
        HoustonServer::tool(GetVelocityReport::class, [])
            ->assertHasErrors(['The repository field is required.']);
    });

    it('ignores whitespace around the repository name', function () {
        Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

        HoustonServer::tool(GetVelocityReport::class, ['repository' => '  acme/app '])
            ->assertOk()
            ->assertSee('"fullName":"acme/app"');
    });

    it('flags a repository that has never been synced as pending, without data and stale', function () {
        Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

        HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app'])
            ->assertOk()
            ->assertSee(['"defaultBranch":null', '"status":"pending"', '"hasData":false', '"finishedAt":null', '"isStale":true', '"error":null']);
    });
});

describe('velocity report', function () {
    beforeEach(fn () => Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']));

    it('rejects an include_bots flag that is not a boolean', function () {
        HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'include_bots' => 'sometimes'])
            ->assertHasErrors(['The include bots field must be true or false.']);
    });

    it('swaps a custom range given back to front', function () {
        HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'from' => '2026-09-15', 'to' => '2026-09-01'])
            ->assertOk()
            ->assertSee(['"days":15', '"custom":true', '"from":"2026-09-01T00:00:00+00:00"']);
    });

    it('keeps a custom range within the repository history and never past today', function () {
        HoustonServer::tool(GetVelocityReport::class, ['repository' => 'acme/app', 'from' => '2020-01-01', 'to' => '2030-01-01'])
            ->assertOk()
            ->assertSee(['"days":181', '"custom":true', '"from":"2026-04-10T00:00:00+00:00"'])
            ->assertDontSee('2020-01-01');
    });
});

describe('pull request listing', function () {
    beforeEach(function () {
        $this->repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    });

    it('lists every open pull request regardless of age', function () {
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(200))->create(['number' => 1]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDay())->create(['number' => 2]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create(['number' => 3]);

        expect(listedNumbers(['state' => 'open', 'days' => 7]))->toBe([2, 1]);
    });

    it('lists pull requests closed without merging within the window', function () {
        PullRequest::factory()->for($this->repository)->create(['number' => 1, 'state' => PullRequestState::Closed, 'opened_at' => $this->now->subDays(3), 'closed_at' => $this->now->subDays(2)]);
        PullRequest::factory()->for($this->repository)->create(['number' => 2, 'state' => PullRequestState::Closed, 'opened_at' => $this->now->subDays(20), 'closed_at' => $this->now->subDays(10)]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 1)->create(['number' => 3]);

        HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'state' => 'closed', 'days' => 7])
            ->assertOk()
            ->assertSee(['"state":"closed"', '"total":1', '"number":1,', '"state":"CLOSED"'])
            ->assertDontSee(['"number":2,', '"number":3,']);
    });

    it('sorts pull requests by the requested order', function (string $sort, array $expected) {
        // #1 opened first, small, merged after 2 hours; #2 large, merged after 30 hours; #3 opened last, still open.
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(3), mergedAfter: 2)->create(['number' => 1, 'additions' => 10, 'deletions' => 0]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays(2), mergedAfter: 30)->create(['number' => 2, 'additions' => 500, 'deletions' => 100]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subHours(5))->create(['number' => 3, 'additions' => 50, 'deletions' => 0]);

        expect(listedNumbers(['state' => 'all', 'sort' => $sort, 'days' => 7]))->toBe($expected);
    })->with([
        'most recent state change first' => ['recent', [3, 2, 1]],
        'oldest opened first' => ['oldest', [1, 2, 3]],
        'most changed lines first' => ['largest', [2, 3, 1]],
        'longest time to merge, close or now first' => ['slowest', [2, 3, 1]],
    ]);

    it('includes bot pull requests when filtering by a bot author', function () {
        PullRequest::factory()->for($this->repository)->bot()->lifecycle($this->now->subDay(), mergedAfter: 1)->create(['number' => 100]);
        PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDay(), mergedAfter: 1)->create(['number' => 1]);

        expect(listedNumbers([]))->toBe([1])
            ->and(listedNumbers(['author' => 'dependabot[bot]']))->toBe([100]);
    });

    it('reports the total even when the limit cuts the list short', function () {
        foreach (range(1, 3) as $number) {
            PullRequest::factory()->for($this->repository)->lifecycle($this->now->subDays($number), mergedAfter: 1)->create(['number' => $number]);
        }

        HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', 'limit' => 2])
            ->assertOk()
            ->assertSee(['"days":30', '"from":null', '"to":null', '"state":"merged"', '"total":3']);

        expect(listedNumbers(['limit' => 2]))->toBe([1, 2]);
    });

    it('rejects filters it does not understand', function (array $arguments, string $message) {
        HoustonServer::tool(ListPullRequests::class, ['repository' => 'acme/app', ...$arguments])
            ->assertHasErrors([$message]);
    })->with([
        'unknown state' => [['state' => 'draft'], 'The selected state is invalid.'],
        'unknown sort' => [['sort' => 'popular'], 'The selected sort is invalid.'],
        'limit below one' => [['limit' => 0], 'The limit field must be at least 1.'],
        'limit above one hundred' => [['limit' => 101], 'The limit field must not be greater than 100.'],
        'unsupported window' => [['days' => 60], 'The selected days is invalid.'],
    ]);
});

describe('feedback prompt', function () {
    it('reviews every watched repository over the last 30 days by default', function () {
        HoustonServer::prompt(GiveFeedback::class)
            ->assertOk()
            ->assertSee(['every repository Houston watches (start with list-repositories', 'over the last 30 days', 'get-velocity-report (days: 30)'])
            ->assertDontSee('Pay particular attention to');
    });

    it('falls back to the last days when only half of a custom range is given', function () {
        HoustonServer::prompt(GiveFeedback::class, ['from' => '2026-09-01', 'days' => 7])
            ->assertSee(['over the last 7 days', 'get-velocity-report (days: 7)'])
            ->assertDontSee('2026-09-01');
    });

    it('falls back to 30 days for a window the dashboards do not offer', function () {
        HoustonServer::prompt(GiveFeedback::class, ['days' => 3])
            ->assertSee(['over the last 30 days', 'days: 30']);
    });
});
