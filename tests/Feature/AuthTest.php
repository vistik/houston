<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

it('stays open while the login is off', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('login'))->assertRedirect(route('home'));
    $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'password'])->assertNotFound();
});

describe('with the login on but not created yet', function () {
    beforeEach(function () {
        config(['houston.auth' => true]);
    });

    it('shows guests the install guide', function (string $route) {
        $this->get(route($route))->assertRedirect(route('landing.install'));
    })->with(['home', 'wall', 'settings.syncs.index', 'login']);

    it('explains how to create the login', function () {
        $this->get(route('landing.install'))
            ->assertOk()
            ->assertViewIs('landing.install')
            ->assertSee('it has no login yet');
    });

    it('answers JSON requests from guests with a 401', function () {
        $this->getJson(route('home'))->assertUnauthorized();
    });
});

describe('with the login on', function () {
    beforeEach(function () {
        config(['houston.auth' => true]);
        $this->user = User::factory()->create(['email' => 'admin@example.com']);
    });

    it('sends guests to the login page', function (string $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    })->with(['home', 'wall', 'repositories.index', 'teams.index', 'settings.syncs.index']);

    it('answers JSON requests from guests with a 401', function () {
        $this->getJson(route('home'))->assertUnauthorized();
    });

    it('shows the login page', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    });

    it('gives shared links the social preview image', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('landing/og.png').'">', false);
    });

    it('logs in and returns to the page the guest asked for', function () {
        $this->get(route('teams.index'));

        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('teams.index'));

        $this->assertAuthenticatedAs($this->user);
        $this->get(route('home'))->assertOk();
    });

    it('rejects a wrong password', function () {
        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('throttles repeated attempts', function () {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        $this->post(route('login.store'), ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertTooManyRequests();

        $this->assertGuest();
    });

    it('logs out', function () {
        $this->actingAs($this->user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });
});

describe('houston:install', function () {
    beforeEach(function () {
        $this->environmentPath = sys_get_temp_dir().'/houston-install-'.uniqid();
        File::ensureDirectoryExists($this->environmentPath);
        File::put("{$this->environmentPath}/.env", "APP_NAME=Houston\nHOUSTON_AUTH=false\nHOUSTON_LANDING=true\n");
        $this->app->useEnvironmentPath($this->environmentPath);
    });

    afterEach(fn () => File::deleteDirectory($this->environmentPath));

    it('turns the login on and creates it', function () {
        $this->artisan('houston:install')
            ->expectsConfirmation('Should Houston ask for a login?', 'yes')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm the password', 'secret-password')
            ->assertSuccessful();

        expect(User::sole()->email)->toBe('admin@example.com')
            ->and(File::get("{$this->environmentPath}/.env"))->toContain('HOUSTON_AUTH=true')
            ->toContain('HOUSTON_LANDING=false')
            ->not->toContain('HOUSTON_LANDING=true');
    });

    it('replaces the existing login', function () {
        User::factory()->create(['email' => 'old@example.com']);

        $this->artisan('houston:install')
            ->expectsConfirmation('Should Houston ask for a login?', 'yes')
            ->expectsConfirmation('Replace the login for old@example.com?', 'yes')
            ->expectsQuestion('Email', 'new@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm the password', 'secret-password')
            ->assertSuccessful();

        expect(User::sole()->email)->toBe('new@example.com');
    });

    it('keeps the existing login when asked to', function () {
        User::factory()->create(['email' => 'old@example.com']);

        $this->artisan('houston:install')
            ->expectsConfirmation('Should Houston ask for a login?', 'yes')
            ->expectsConfirmation('Replace the login for old@example.com?', 'no')
            ->assertSuccessful();

        expect(User::sole()->email)->toBe('old@example.com')
            ->and(File::get("{$this->environmentPath}/.env"))->toContain('HOUSTON_AUTH=true');
    });

    it('leaves the login off', function () {
        $this->artisan('houston:install')
            ->expectsConfirmation('Should Houston ask for a login?', 'no')
            ->assertSuccessful();

        expect(User::count())->toBe(0)
            ->and(File::get("{$this->environmentPath}/.env"))->toContain('HOUSTON_AUTH=false')
            ->toContain('HOUSTON_LANDING=false');
    });

    it('skips the questions when it cannot ask them', function () {
        $this->artisan('houston:install', ['--no-interaction' => true])->assertSuccessful();

        expect(User::count())->toBe(0);
    });
});
