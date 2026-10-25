<?php

use App\Enums\RepositorySyncStatus;
use App\GitHub\GitHubClient;
use App\Jobs\SyncRepository;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TimelineEvent;
use App\Providers\AppServiceProvider;
use App\Settings\OverviewLayouts;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    config(['houston.demo' => true, 'houston.github_token' => 'secret-token']);
});

it('tells the pages it is the demo', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo', true));

    config(['houston.demo' => false]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo', false));
});

it('refuses to watch, remove or change repositories', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);

    $this->post(route('repositories.store'), ['repository' => 'laravel/framework'])->assertForbidden();
    $this->delete(route('repositories.destroy', $repository))->assertForbidden();
    $this->put(route('repositories.lookback', $repository), ['lookback_days' => 365])->assertForbidden();
    $this->put(route('repositories.excluded-workflows', $repository), ['workflow_ids' => [1]])->assertForbidden();

    expect(Repository::query()->get()->map->fullName()->all())->toBe(['acme/api'])
        ->and($repository->fresh()->lookback_days)->toBe($repository->lookback_days)
        ->and($repository->fresh()->excludedWorkflowIds())->toBe([]);
});

it('never lets visitors sync from GitHub', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
    $repository->syncState()->update(['finished_at' => now()->subDay()]);

    $this->post(route('repositories.sync', $repository))->assertForbidden();
    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'api']))->assertOk();
    $this->get(route('repositories.index'))->assertOk();

    Queue::assertNothingPushed();
    expect($repository->syncState()->status)->toBe(RepositorySyncStatus::Succeeded);
});

it('keeps syncing on the schedule', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);

    $this->artisan('houston:sync --queue')->assertSuccessful();

    Queue::assertPushed(SyncRepository::class, fn (SyncRepository $job): bool => $job->repository->is($repository));
});

it('never talks to GitHub for a visitor', function () {
    Http::fake();
    Process::fake();
    request()->setLaravelSession(app('session.store'));

    expect(fn () => app(GitHubClient::class)->rateLimits())->toThrow(RuntimeException::class, 'does not talk to GitHub for demo visitors');

    Http::assertNothingSent();
    Process::assertNothingRan();
});

it('hides the settings and repository suggestions', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'settings' => fn () => route('settings'),
    'syncs' => fn () => route('settings.syncs.index'),
    'log' => fn () => route('settings.log.index'),
    'defaults' => fn () => route('settings.defaults.edit'),
    'benchmarks' => fn () => route('settings.benchmarks.edit'),
    'teams' => fn () => route('settings.teams.index'),
    'events' => fn () => route('settings.events.index'),
    'suggestions' => fn () => route('repositories.suggestions', ['q' => 'acme']),
]);

it('refuses to change teams, timeline events or report settings', function () {
    $team = Team::factory()->create(['name' => 'Platform']);
    $event = TimelineEvent::factory()->create(['title' => 'Launched']);

    $this->post(route('settings.teams.store'), ['name' => 'Hacked'])->assertNotFound();
    $this->delete(route('settings.teams.destroy', $team))->assertNotFound();
    $this->put(route('settings.events.update', $event), ['title' => 'Hacked', 'happened_on' => '2026-10-01'])->assertNotFound();
    $this->put(route('settings.defaults.update'), [])->assertNotFound();

    expect(Team::query()->pluck('name')->all())->toBe(['Platform'])
        ->and($event->fresh()->title)->toBe('Launched');
});

it('keeps layouts and views to the visitor who picked them', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'api']);
    $team = Team::factory()->create();

    $this->put(route('overview-layouts.update'), ['repositories' => 'table'])->assertRedirect();
    $this->put(route('repositories.view', $repository), ['view' => 'ci'])->assertRedirect();
    $this->put(route('repositories.card-view', $repository), ['card_view' => 'delivery'])->assertRedirect();
    $this->put(route('teams.card-view', $team), ['card_view' => 'review'])->assertRedirect();
    $this->put(route('home.pins'), ['teams' => [], 'repositories' => []])->assertRedirect();

    $this->get(route('repositories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('layouts.repositories', 'table')
        ->where('repositories.0.view', 'ci')
        ->where('repositories.0.cardView', 'delivery'));
    $this->get(route('teams.index'))->assertInertia(fn (Assert $page) => $page->where('teams.0.cardView', 'review'));

    expect(Setting::query()->count())->toBe(0)
        ->and($repository->fresh()->view)->toBeNull()
        ->and($repository->fresh()->card_view)->toBeNull()
        ->and($team->fresh()->card_view)->toBeNull();

    // The next visitor starts from what was saved.
    $this->flushSession();

    $this->get(route('repositories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('layouts.repositories', OverviewLayouts::current()->repositories->value)
        ->where('repositories.0.view', 'full')
        ->where('repositories.0.cardView', 'full'));
});

it('has no login and never shows the environment on error pages', function () {
    config(['houston.auth' => true, 'app.debug' => true]);

    (new AppServiceProvider(app()))->boot();

    expect(config('houston.auth'))->toBeFalse()
        ->and(config('app.debug'))->toBeFalse();
});

it('schedules syncs and never wipes the synced data', function () {
    $this->travelTo(now()->startOfDay());

    $commands = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event->isDue(app()) && $event->filtersPass(app()))
        ->map->command
        ->implode("\n");

    expect($commands)->toContain('houston:sync')->not->toContain('migrate:fresh');
});
