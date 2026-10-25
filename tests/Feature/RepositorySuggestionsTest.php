<?php

use App\Models\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A repository as GitHub's REST API returns it.
 *
 * @return array<string, mixed>
 */
function gitHubRepository(string $fullName, bool $private = false, int $stars = 0): array
{
    [$owner, $name] = explode('/', $fullName);

    return [
        'full_name' => $fullName,
        'name' => $name,
        'owner' => ['login' => $owner, 'avatar_url' => "https://avatars.githubusercontent.com/{$owner}"],
        'private' => $private,
        'stargazers_count' => $stars,
        'description' => "The {$name} repository",
    ];
}

it('suggests repositories the token can access, private ones included', function () {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'api']);

    Http::fake(['api.github.com/user/repos*' => Http::response([
        gitHubRepository('acme/website'),
        gitHubRepository('acme/api', private: true, stars: 3),
        gitHubRepository('globex/apples'),
    ])]);

    $this->getJson(route('repositories.suggestions', ['q' => 'ap']))
        ->assertOk()
        ->assertExactJson([
            'complete' => true,
            'suggestions' => [
                [
                    'fullName' => 'acme/api',
                    'owner' => 'acme',
                    'name' => 'api',
                    'avatarUrl' => 'https://avatars.githubusercontent.com/acme',
                    'private' => true,
                    'stars' => 3,
                    'description' => 'The api repository',
                    'watched' => true,
                ],
                [
                    'fullName' => 'globex/apples',
                    'owner' => 'globex',
                    'name' => 'apples',
                    'avatarUrl' => 'https://avatars.githubusercontent.com/globex',
                    'private' => false,
                    'stars' => 0,
                    'description' => 'The apples repository',
                    'watched' => false,
                ],
            ],
        ]);

    // Two characters are too few to spend the search budget on.
    Http::assertSentCount(1);
});

it('fills in public repositories from the search API for longer terms', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([gitHubRepository('acme/frame-tools')]),
        'api.github.com/search/repositories*' => Http::response(['items' => [
            gitHubRepository('laravel/framework', stars: 33000),
            gitHubRepository('acme/frame-tools'),
        ]]),
    ]);

    $this->getJson(route('repositories.suggestions', ['q' => 'frame']))
        ->assertOk()
        ->assertJsonPath('suggestions.*.fullName', ['acme/frame-tools', 'laravel/framework'])
        ->assertJsonPath('suggestions.1.stars', 33000);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.github.com/search/repositories')
        && $request['q'] === 'frame in:name');
});

it('lists the repositories of the owner typed so far', function (string $input) {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([gitHubRepository('acme/private-web', private: true), gitHubRepository('globex/web')]),
        'api.github.com/users/acme/repos*' => Http::response([gitHubRepository('acme/docs'), gitHubRepository('acme/web')]),
    ]);

    $this->getJson(route('repositories.suggestions', ['q' => $input]))
        ->assertOk()
        ->assertJsonPath('suggestions.*.fullName', ['acme/private-web', 'acme/docs', 'acme/web']);
})->with(['acme/', 'https://github.com/acme/']);

it('ranks repositories whose name starts with what follows the owner first', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
        'api.github.com/users/acme/repos*' => Http::response([gitHubRepository('acme/old-web'), gitHubRepository('acme/docs'), gitHubRepository('acme/website')]),
    ]);

    $this->getJson(route('repositories.suggestions', ['q' => 'acme/we']))
        ->assertOk()
        ->assertJsonPath('suggestions.*.fullName', ['acme/website', 'acme/old-web']);
});

it('caches what GitHub returned', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response([gitHubRepository('acme/api')])]);

    $this->getJson(route('repositories.suggestions', ['q' => 'a']))->assertJsonPath('suggestions.0.fullName', 'acme/api');
    $this->getJson(route('repositories.suggestions', ['q' => 'ac']))->assertJsonPath('suggestions.0.fullName', 'acme/api');

    Http::assertSentCount(1);
});

it('spends the whole search budget, which is smaller than the reserve kept for syncs', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([]),
        'api.github.com/search/repositories*' => Http::response(['items' => [gitHubRepository('laravel/framework')]], 200, [
            'x-ratelimit-resource' => 'search',
            'x-ratelimit-limit' => '30',
            'x-ratelimit-remaining' => '29',
            'x-ratelimit-used' => '1',
            'x-ratelimit-reset' => (string) now()->addMinute()->getTimestamp(),
        ]),
    ]);

    $this->getJson(route('repositories.suggestions', ['q' => 'frame']))->assertJsonPath('complete', true);
    $this->getJson(route('repositories.suggestions', ['q' => 'framew']))->assertJsonPath('complete', true);

    Http::assertSentCount(3);
});

it('keeps suggesting accessible repositories while the search API is rate limited', function () {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([gitHubRepository('acme/frame-tools')]),
        'api.github.com/search/repositories*' => Http::response(['message' => 'API rate limit exceeded'], 403, [
            'x-ratelimit-resource' => 'search',
            'x-ratelimit-limit' => '30',
            'x-ratelimit-remaining' => '0',
            'x-ratelimit-used' => '30',
            'x-ratelimit-reset' => (string) now()->addMinute()->getTimestamp(),
        ]),
    ]);

    $this->getJson(route('repositories.suggestions', ['q' => 'frame']))
        ->assertOk()
        ->assertJsonPath('complete', false)
        ->assertJsonPath('suggestions.*.fullName', ['acme/frame-tools']);

    // Until the limit resets the search API is not asked again.
    $this->getJson(route('repositories.suggestions', ['q' => 'frames']))->assertOk()->assertJsonPath('complete', false);

    Http::assertSentCount(2);
});

it('answers without suggestions when GitHub fails', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response('Bad Gateway', 502)]);

    $this->getJson(route('repositories.suggestions', ['q' => 'ap']))
        ->assertOk()
        ->assertExactJson(['complete' => false, 'suggestions' => []]);

    // Someone typing would rather see nothing than wait for retries.
    Http::assertSentCount(1);
});

it('does not ask GitHub about input that cannot name a repository', function () {
    Http::fake();

    $this->getJson(route('repositories.suggestions', ['q' => 'not a repo']))
        ->assertOk()
        ->assertExactJson(['complete' => true, 'suggestions' => []]);

    Http::assertNothingSent();
});

it('rejects overly long input', function () {
    $this->getJson(route('repositories.suggestions', ['q' => str_repeat('a', 201)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q' => 'must not be greater than 200 characters']);
});

it('answers guests with a 401 while the login is on', function () {
    config(['houston.auth' => true]);

    $this->getJson(route('repositories.suggestions', ['q' => 'ap']))->assertUnauthorized();
});
