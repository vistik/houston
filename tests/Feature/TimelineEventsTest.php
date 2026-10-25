<?php

use App\Models\Repository;
use App\Models\SystemEvent;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TimelineEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
});

it('lists events, newest first', function () {
    TimelineEvent::factory()->create(['title' => 'Introduced AI reviews', 'happened_on' => '2026-08-01']);
    TimelineEvent::factory()->create(['title' => 'Deployed a Slack bot for review reminders', 'happened_on' => '2026-09-15']);

    $this->get(route('settings.events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/events')
            ->where('events.0.title', 'Deployed a Slack bot for review reminders')
            ->where('events.0.date', '2026-09-15')
            ->where('events.1.title', 'Introduced AI reviews'));
});

it('adds, changes and removes an event, and logs each', function () {
    $this->post(route('settings.events.store'), ['title' => '  Introduced AI reviews ', 'happened_on' => '2026-09-01'])
        ->assertRedirect(route('settings.events.index'));

    $event = TimelineEvent::sole();
    expect($event->title)->toBe('Introduced AI reviews')
        ->and($event->happened_on->toDateString())->toBe('2026-09-01');

    $this->put(route('settings.events.update', $event), ['title' => 'Switched to Claude Team', 'happened_on' => '2026-09-03'])
        ->assertRedirect(route('settings.events.index'));

    expect($event->fresh()->title)->toBe('Switched to Claude Team')
        ->and($event->fresh()->happened_on->toDateString())->toBe('2026-09-03');

    $this->delete(route('settings.events.destroy', $event));

    expect(TimelineEvent::count())->toBe(0)
        ->and(SystemEvent::query()->where('type', 'like', 'timeline.%')->count())->toBe(3);
});

it('rejects an event without a title or on a day yet to come', function () {
    $this->post(route('settings.events.store'), ['title' => '', 'happened_on' => '2026-09-01'])->assertSessionHasErrors('title');
    $this->post(route('settings.events.store'), ['title' => 'Introduced AI reviews', 'happened_on' => '2026-10-08'])->assertSessionHasErrors('happened_on');
    $this->post(route('settings.events.store'), ['title' => 'Introduced AI reviews', 'happened_on' => 'last week'])->assertSessionHasErrors('happened_on');

    expect(TimelineEvent::count())->toBe(0);
});

it('marks events on the repository report', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    TimelineEvent::factory()->create(['title' => 'Introduced AI reviews', 'happened_on' => '2026-09-01']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.title', 'Introduced AI reviews')
            ->where('event', null)
            ->where('filters.event', null));
});

it('reports a repository from an event through today, compared with the stretch before', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);
    $event = TimelineEvent::factory()->create(['title' => 'Introduced AI reviews', 'happened_on' => '2026-09-08']);

    // The event wins over any period in the request.
    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'event' => $event->id, 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-09-08')
            ->where('filters.to', '2026-10-07')
            ->where('filters.days', 30)
            ->where('filters.event', $event->id)
            ->where('event.title', 'Introduced AI reviews')
            ->has('event.historyFrom')
            ->where('report.period.days', 30)
            // Charted from the start of the 30 days before, so both sides show.
            ->has('report.trend', 60)
            ->where('report.trend.0.date', '2026-08-09')
            ->where('report.trend.30.date', '2026-09-08')
            // Right-now metrics say so, so the comparison can leave them out.
            ->where('report.kpis.openPullRequests.snapshot', true)
            ->missing('report.kpis.cycleTime.snapshot'));
});

it('falls back to the usual period for an event that no longer exists', function () {
    Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'app']);

    $this->get(route('repositories.show', ['owner' => 'acme', 'name' => 'app', 'event' => 404, 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.days', 7)
            ->where('filters.from', null)
            ->where('event', null));
});

it('reports a team from an event through today', function () {
    $team = Team::factory()->create();
    TeamMembership::factory()->for($team)->create(['login' => 'alice']);
    $event = TimelineEvent::factory()->create(['happened_on' => '2026-09-28']);

    $this->get(route('teams.show', ['team' => $team, 'event' => $event->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-09-28')
            ->where('filters.to', '2026-10-07')
            ->where('filters.event', $event->id)
            ->where('event.id', $event->id)
            ->has('events', 1));
});
