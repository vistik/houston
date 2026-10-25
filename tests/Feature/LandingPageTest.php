<?php

use App\Enums\LandingFeature;
use App\Metrics\MetricIcons;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

describe('with the landing page off', function () {
    it('sends / to the dashboard', function () {
        $this->get(route('landing'))->assertRedirect(route('home'));
    });

    it('keeps the install guide hidden', function () {
        $this->get(route('landing.install'))->assertNotFound();
    });
});

describe('with the landing page on', function () {
    beforeEach(function () {
        config(['houston.landing' => true]);
    });

    it('shows the website at / while the dashboard has repositories', function () {
        Queue::fake();
        Repository::factory()->create();

        $this->get(route('landing'))
            ->assertOk()
            ->assertViewIs('landing.home')
            ->assertSee('Mission control for your engineering team.')
            ->assertSee('Open source · Self-hosted · All systems go')
            ->assertSee(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home')->has('repositories', 1));
    });

    it('has a section for every feature in the menu', function () {
        $response = $this->get(route('landing'))->assertOk();

        foreach (LandingFeature::cases() as $feature) {
            $response->assertSee('<section id="'.$feature->value.'"', false);
        }
    });

    it('links every metric to its own page for the first repository', function () {
        Queue::fake();
        Repository::factory()->create(['owner' => 'zeta', 'name' => 'app']);
        Repository::factory()->create(['owner' => 'acme', 'name' => 'web']);
        Repository::factory()->create(['owner' => 'acme', 'name' => 'api']);

        $html = $this->get(route('landing'))->assertOk()->getContent();

        foreach (array_keys(MetricIcons::Glyphs) as $metric) {
            expect($html)->toContain('href="'.route('repositories.metric', ['owner' => 'acme', 'name' => 'api', 'metric' => $metric]).'"');
        }

        expect(substr_count($html, 'href="'.route('repositories.show', ['owner' => 'acme', 'name' => 'api']).'/metrics/'))->toBe(count(MetricIcons::Glyphs));
    });

    it('lists the metrics without links before there is a repository', function () {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('Time to merge')
            ->assertDontSee('href="'.route('repositories.index').'/', false);
    });

    it('shows the install guide', function () {
        $this->get(route('landing.install'))
            ->assertOk()
            ->assertViewIs('landing.install');
    });

    it('says the website is hosted on Laravel Cloud', function () {
        $this->get(route('landing'))->assertOk()->assertSee('Hosted on')->assertSee('https://cloud.laravel.com', false);
    });

    describe('and the login on', function () {
        beforeEach(function () {
            config(['houston.auth' => true]);
            User::factory()->create();
        });

        it('links guests to the login', function () {
            $this->get(route('landing'))
                ->assertOk()
                ->assertViewIs('landing.home')
                ->assertSee(route('login'));
        });

        it('shows the website to people who are logged in too', function () {
            $this->actingAs(User::first())
                ->get(route('landing'))
                ->assertOk()
                ->assertViewIs('landing.home');
        });

        it('keeps the dashboard behind the login', function () {
            $this->get(route('home'))->assertRedirect(route('login'));
            $this->getJson(route('home'))->assertUnauthorized();
        });
    });
});
