<?php

use App\Models\Repository;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    $this->repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    $this->team = Team::factory()->create(['name' => 'Platform']);
    TeamMembership::factory()->for($this->team)->create(['login' => 'alice']);
});

it('shows teams and repositories and defers their summaries', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->missing('filters.show')
            ->where('sections', ['teams' => true, 'repositories' => true])
            ->where('pins', ['teams' => null, 'repositories' => null])
            ->has('teams', 1)
            ->has('repositories', 1)
            ->missing('summaries')
            ->missing('teamSummaries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has("summaries.{$this->repository->id}.kpis.throughput")
                ->has("summaries.{$this->repository->id}.series.deployments")
                ->has("teamSummaries.{$this->team->id}.kpis.throughput")
                ->has("teamSummaries.{$this->team->id}.series.cycleTime")
                ->has("teamSummaries.{$this->team->id}.series.openPullRequests")
                ->missing("teamSummaries.{$this->team->id}.series.deployments")
                ->has("teamSummaries.{$this->team->id}.period.granularity")));
});

it('hides a section with nothing of it picked and computes none of its summaries', function (array $pins, array $sections) {
    $this->put(route('home.pins'), $pins)->assertRedirect();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pins', $pins)
            ->where('sections', $sections)
            ->has('teams', $sections['teams'] ? 1 : 0)
            ->has('repositories', $sections['repositories'] ? 1 : 0)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('summaries', fn ($summaries) => collect($summaries)->isNotEmpty() === $sections['repositories'])
                ->where('teamSummaries', fn ($summaries) => collect($summaries)->isNotEmpty() === $sections['teams'])));
})->with([
    'no teams' => [['teams' => [], 'repositories' => null], ['teams' => false, 'repositories' => true]],
    'no repositories' => [['teams' => null, 'repositories' => []], ['teams' => true, 'repositories' => false]],
    'nothing' => [['teams' => [], 'repositories' => []], ['teams' => false, 'repositories' => false]],
]);

it('leaves out the teams section while there are no teams', function () {
    $this->team->delete();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('sections', ['teams' => false, 'repositories' => true]));
});

it('keeps the repositories section to say how to add one while there are none', function () {
    $this->repository->delete();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.repositories', true)
            ->has('repositories', 0));
});

it('shows only the teams and repositories picked for it', function () {
    $other = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'web']);
    $otherTeam = Team::factory()->create(['name' => 'Payments']);

    $this->put(route('home.pins'), ['teams' => [$otherTeam->id], 'repositories' => [$other->id]])
        ->assertRedirect();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pins', ['teams' => [$otherTeam->id], 'repositories' => [$other->id]])
            ->has('choices.teams', 2)
            ->has('choices.repositories', 2)
            ->has('teams', 1, fn (Assert $team) => $team->where('name', 'Payments')->etc())
            ->has('repositories', 1, fn (Assert $repository) => $repository->where('fullName', 'acme/web')->etc())
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('summaries', 1)
                ->has("summaries.{$other->id}")
                ->has('teamSummaries', 1)
                ->has("teamSummaries.{$otherTeam->id}")));
});

it('shows all of a kind, including ones added later, when all of it is picked', function () {
    $this->put(route('home.pins'), ['teams' => [$this->team->id], 'repositories' => null]);

    Repository::factory()->synced()->create();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pins.repositories', null)
            ->has('teams', 1)
            ->has('repositories', 2));
});

it('forgets picks of teams and repositories that are gone', function () {
    $otherTeam = Team::factory()->create();

    $this->put(route('home.pins'), ['teams' => [$this->team->id, $otherTeam->id], 'repositories' => [$this->repository->id]]);

    $this->team->delete();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('pins', ['teams' => [$otherTeam->id], 'repositories' => [$this->repository->id]]));
});

it('shows all of a kind again once everything picked of it is gone', function () {
    $other = Repository::factory()->synced()->create();

    $this->put(route('home.pins'), ['teams' => null, 'repositories' => [$other->id]]);

    $other->delete();

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pins.repositories', null)
            ->where('sections.repositories', true)
            ->has('repositories', 1));
});

it('rejects picks of teams and repositories that do not exist', function () {
    $this->put(route('home.pins'), ['teams' => [999], 'repositories' => ['nope']])
        ->assertSessionHasErrors(['teams.0', 'repositories.0']);
});

it('requires picks for both kinds', function () {
    $this->put(route('home.pins'), [])
        ->assertSessionHasErrors(['teams', 'repositories']);
});

it('keeps showing all of a kind that was saved with nothing picked, from before an empty pick hid it', function () {
    Setting::put('home_pins', ['teams' => [], 'repositories' => [$this->repository->id]]);

    (require database_path('migrations/2026_10_23_074642_show_all_for_empty_home_pins.php'))->up();

    expect(Setting::get('home_pins'))->toBe(['teams' => null, 'repositories' => [$this->repository->id]]);
});
