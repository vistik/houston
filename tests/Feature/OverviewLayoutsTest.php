<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('lays out teams and repositories as cards until a layout is picked', function (string $route) {
    $this->get(route($route))
        ->assertInertia(fn (Assert $page) => $page->where('layouts', ['teams' => 'cards', 'repositories' => 'cards']));
})->with(['home', 'repositories.index', 'teams.index']);

it('remembers the picked layout on every overview', function () {
    $this->put(route('overview-layouts.update'), ['repositories' => 'table'])
        ->assertRedirect();

    foreach (['home', 'repositories.index', 'teams.index'] as $route) {
        $this->get(route($route))
            ->assertInertia(fn (Assert $page) => $page->where('layouts', ['teams' => 'cards', 'repositories' => 'table']));
    }
});

it('keeps the layout of the kind that was not picked', function () {
    $this->put(route('overview-layouts.update'), ['teams' => 'table', 'repositories' => 'table']);
    $this->put(route('overview-layouts.update'), ['teams' => 'cards']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('layouts', ['teams' => 'cards', 'repositories' => 'table']));
});

it('rejects layouts it does not know', function () {
    $this->put(route('overview-layouts.update'), ['teams' => 'mosaic', 'repositories' => ''])
        ->assertSessionHasErrors(['teams', 'repositories']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('layouts', ['teams' => 'cards', 'repositories' => 'cards']));
});

it('falls back to cards when a saved layout is no longer known', function () {
    Setting::put('overview_layouts', ['teams' => 'retired', 'repositories' => 'table']);

    $this->get(route('teams.index'))
        ->assertInertia(fn (Assert $page) => $page->where('layouts', ['teams' => 'cards', 'repositories' => 'table']));
});
