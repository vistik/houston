<?php

use App\Metrics\Period;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Reports\ReportCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
});

it('lists teams with their memberships and the authors to pick members from', function () {
    $team = Team::factory()->create(['name' => 'Platform']);
    TeamMembership::factory()->for($team)->create(['login' => 'alice', 'joined_on' => '2026-09-01']);
    TeamMembership::factory()->for($team)->create(['login' => 'bob', 'left_on' => '2026-09-01']);
    PullRequest::factory()->create(['author' => 'alice', 'author_avatar_url' => 'https://avatars.example/alice']);
    PullRequest::factory()->bot()->create();

    $this->get(route('settings.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/teams')
            ->where('authors', ['alice'])
            ->has('teams', 1, fn (Assert $team) => $team
                ->where('name', 'Platform')
                ->where('memberships.0', fn ($alice) => $alice['login'] === 'alice' && $alice['current'] === true && $alice['joinedOn'] === '2026-09-01' && $alice['avatarUrl'] === 'https://avatars.example/alice')
                ->where('memberships.1.current', false)
                ->etc()));
});

it('creates, renames and deletes a team', function () {
    $this->post(route('settings.teams.store'), ['name' => 'Developer Experience'])
        ->assertRedirect(route('settings.teams.index'));

    $team = Team::sole();
    expect($team->slug)->toBe('developer-experience');

    $this->put(route('settings.teams.update', $team), ['name' => 'Platform'])
        ->assertRedirect(route('settings.teams.index'));

    expect($team->fresh()->slug)->toBe('platform');

    $this->delete(route('settings.teams.destroy', $team->fresh()));

    expect(Team::count())->toBe(0);
});

it('rejects a team without a name or with the name of another team', function () {
    Team::factory()->create(['name' => 'Platform']);

    $this->post(route('settings.teams.store'), ['name' => ''])->assertSessionHasErrors('name');
    $this->post(route('settings.teams.store'), ['name' => 'Platform'])->assertSessionHasErrors('name');
});

it('adds a member by GitHub username', function () {
    $team = Team::factory()->create();

    $this->post(route('settings.teams.memberships.store', $team), ['login' => '@alice', 'joined_on' => '2026-10-01'])
        ->assertRedirect(route('settings.teams.index'));

    $membership = TeamMembership::sole();
    expect($membership->login)->toBe('alice')
        ->and($membership->joined_on->toDateString())->toBe('2026-10-01')
        ->and($membership->left_on)->toBeNull();
});

it('moves a member who joins another team from a given day', function () {
    $platform = Team::factory()->create();
    $payments = Team::factory()->create();
    $previous = TeamMembership::factory()->for($platform)->create(['login' => 'alice']);

    $this->post(route('settings.teams.memberships.store', $payments), ['login' => 'Alice', 'joined_on' => '2026-10-04'])
        ->assertSessionHasNoErrors();

    expect($previous->fresh()->left_on->toDateString())->toBe('2026-10-04')
        ->and($payments->memberships()->sole()->joined_on->toDateString())->toBe('2026-10-04');
});

it('refuses memberships that would put someone on two teams at once', function () {
    $platform = Team::factory()->create(['name' => 'Platform']);
    $payments = Team::factory()->create();
    TeamMembership::factory()->for($platform)->create(['login' => 'alice', 'joined_on' => '2026-10-01']);

    // Without a day they would always have been on the team, including while on Platform.
    $this->post(route('settings.teams.memberships.store', $payments), ['login' => 'alice'])
        ->assertSessionHasErrors(['joined_on' => 'alice is on Platform since Oct 1, 2026. Pick a later day, or change that membership first.']);

    // Joining before they joined Platform would overlap with it.
    $this->post(route('settings.teams.memberships.store', $payments), ['login' => 'alice', 'joined_on' => '2026-09-01'])
        ->assertSessionHasErrors('joined_on');

    $this->post(route('settings.teams.memberships.store', $platform), ['login' => 'alice', 'joined_on' => '2026-10-05'])
        ->assertSessionHasErrors(['joined_on' => 'alice is already on Platform since Oct 1, 2026.']);

    expect(TeamMembership::count())->toBe(1);
});

it('rejects logins that are not GitHub usernames', function () {
    $team = Team::factory()->create();

    $this->post(route('settings.teams.memberships.store', $team), ['login' => 'not a login'])->assertSessionHasErrors('login');
    $this->post(route('settings.teams.memberships.store', $team), ['login' => 'alice', 'joined_on' => 'tomorrow'])->assertSessionHasErrors('joined_on');
});

it('changes when a membership runs, unless it would overlap another', function () {
    $platform = Team::factory()->create(['name' => 'Platform']);
    $payments = Team::factory()->create();
    $before = TeamMembership::factory()->for($platform)->create(['login' => 'alice', 'left_on' => '2026-10-04']);
    TeamMembership::factory()->for($payments)->create(['login' => 'alice', 'joined_on' => '2026-10-04']);

    $this->put(route('settings.memberships.update', $before), ['joined_on' => '2026-09-01', 'left_on' => '2026-10-02'])
        ->assertSessionHasNoErrors();

    expect($before->fresh()->joined_on->toDateString())->toBe('2026-09-01')
        ->and($before->fresh()->left_on->toDateString())->toBe('2026-10-02');

    $this->put(route('settings.memberships.update', $before), ['joined_on' => '2026-09-01', 'left_on' => '2026-10-06'])
        ->assertSessionHasErrors('joined_on');

    $this->put(route('settings.memberships.update', $before), ['joined_on' => '2026-09-01', 'left_on' => '2026-08-01'])
        ->assertSessionHasErrors('left_on');
});

it('removes a membership', function () {
    $membership = TeamMembership::factory()->create();

    $this->delete(route('settings.memberships.destroy', $membership))
        ->assertRedirect(route('settings.teams.index'));

    expect(TeamMembership::count())->toBe(0);
});

it('lists teams and defers their summaries', function () {
    $team = Team::factory()->create();
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);

    $this->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/index')
            ->has('teams', 1)
            ->missing('summaries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has("summaries.{$team->id}.kpis.throughput")
                ->missing("summaries.{$team->id}.kpis.ciFailureRate")));
});

it('shows the team report', function () {
    $team = Team::factory()->create(['slug' => 'platform']);
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);
    PullRequest::factory()->for(Repository::factory()->synced())->lifecycle(CarbonImmutable::now()->subDays(2), mergedAfter: 4)->create(['author' => 'alice']);

    $this->get(route('teams.show', ['team' => 'platform', 'days' => 14]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/show')
            ->where('team.slug', 'platform')
            ->where('filters.days', 14)
            ->where('report.kpis.throughput.value', 1)
            ->has('report.trend', 14)
            ->has('report.trends', 4)
            ->missing('report.delivery'));
});

it('rebuilds a cached team report when who was on the team changes', function () {
    $team = Team::factory()->create();
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);
    PullRequest::factory()->lifecycle(CarbonImmutable::now()->subDays(2), mergedAfter: 4)->create(['author' => 'bob']);
    $reports = app(ReportCache::class);

    expect($reports->team($team->fresh(), Period::lastDays(7, 'UTC'))['kpis']['throughput']['value'])->toBe(0);

    $this->travel(1)->seconds();
    $this->post(route('settings.teams.memberships.store', $team), ['login' => 'bob']);

    expect($reports->team($team->fresh(), Period::lastDays(7, 'UTC'))['kpis']['throughput']['value'])->toBe(1);
});

it('narrows the team report to one of the repositories the team works in', function () {
    $team = Team::factory()->create(['slug' => 'platform']);
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);
    $api = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
    $web = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'web']);
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'docs']);
    PullRequest::factory()->for($api)->lifecycle(CarbonImmutable::now()->subDays(2), mergedAfter: 4)->create(['author' => 'alice']);
    PullRequest::factory()->for($web)->lifecycle(CarbonImmutable::now()->subDays(2), mergedAfter: 4)->create(['author' => 'alice']);
    PullRequest::factory()->for($web)->lifecycle(CarbonImmutable::now()->subDays(2), mergedAfter: 4)->create(['author' => 'alice']);

    $this->get(route('teams.show', ['team' => 'platform', 'repository' => 'acme/web']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('repositories', [['fullName' => 'acme/api', 'avatarUrl' => null, 'pullRequests' => 1], ['fullName' => 'acme/web', 'avatarUrl' => null, 'pullRequests' => 2]])
            ->where('filters.repository', 'acme/web')
            ->where('report.kpis.throughput.value', 2));

    $this->get(route('teams.show', ['team' => 'platform', 'repository' => 'acme/docs']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.repository', null)
            ->where('report.kpis.throughput.value', 3));
});
