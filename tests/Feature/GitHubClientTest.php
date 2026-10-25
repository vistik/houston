<?php

use App\GitHub\GitHubClient;
use App\GitHub\GitHubException;
use App\GitHub\RateLimitedException;
use App\GitHub\RateLimits;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
});

it('retries transient GitHub failures', function () {
    Http::fake(['api.github.com/repos/acme/app' => Http::sequence()
        ->push('Bad Gateway', 502)
        ->push('Too Many Requests', 429)
        ->push(['full_name' => 'acme/app'])]);

    expect(app(GitHubClient::class)->get('repos/acme/app'))->toBe(['full_name' => 'acme/app']);

    Http::assertSentCount(3);
});

it('does not retry client errors', function () {
    Http::fake(['api.github.com/repos/acme/missing' => Http::response(['message' => 'Not Found'], 404)]);

    expect(fn () => app(GitHubClient::class)->get('repos/acme/missing'))->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

it('surfaces GraphQL errors', function () {
    Http::fake(['api.github.com/graphql' => Http::response(['errors' => [['message' => 'Something broke'], ['message' => 'Twice']]])]);

    expect(fn () => app(GitHubClient::class)->graphql('query { viewer { login } }'))
        ->toThrow(GitHubException::class, 'Something broke; Twice');
});

it('runs GraphQL queries concurrently and retries the ones that fail', function () {
    Http::fake(['api.github.com/graphql' => function ($request) {
        static $attempts = [];
        $login = $request['variables']['login'];
        $attempts[$login] = ($attempts[$login] ?? 0) + 1;

        return $login === 'bob' && $attempts[$login] === 1
            ? Http::response(['errors' => [['message' => 'Something broke']]])
            : Http::response(['data' => ['user' => ['login' => $login]]]);
    }]);

    $query = 'query($login: String!) { user(login: $login) { login } }';

    expect(app(GitHubClient::class)->graphqlMany([[$query, ['login' => 'alice']], [$query, ['login' => 'bob']]]))
        ->toBe([['user' => ['login' => 'alice']], ['user' => ['login' => 'bob']]]);

    Http::assertSentCount(3);
});

it('rebuilds rate limit exceptions from the arguments a concurrent process reports', function () {
    $exception = new RateLimitedException('graphql', json_decode(json_encode(CarbonImmutable::parse('2026-10-07 12:10:00'))));

    expect($exception->retryAt)->toEqual(CarbonImmutable::parse('2026-10-07 12:10:00'))
        ->and($exception->getMessage())->toBe('GitHub graphql API rate limit reached until 2026-10-07T12:10:00Z.');
});

it('records the rate limits GitHub reports', function () {
    Http::fake(['api.github.com/repos/acme/app' => Http::response(['full_name' => 'acme/app'], 200, [
        'x-ratelimit-resource' => 'core', 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => '4990',
        'x-ratelimit-used' => '10', 'x-ratelimit-reset' => '1791370800',
    ])]);

    app(GitHubClient::class)->get('repos/acme/app');

    expect(app(RateLimits::class)->all()['core'])
        ->toMatchArray(['limit' => 5000, 'remaining' => 4990, 'used' => 10, 'reset' => 1791370800]);
});

it('stops at an exhausted rate limit instead of retrying', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $reset = now()->addMinutes(10)->getTimestamp();

    Http::fake(['api.github.com/repos/acme/app' => Http::response(['message' => 'API rate limit exceeded'], 403, [
        'x-ratelimit-resource' => 'core', 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => '0',
        'x-ratelimit-used' => '5000', 'x-ratelimit-reset' => (string) $reset,
    ])]);

    try {
        app(GitHubClient::class)->get('repos/acme/app');
        $this->fail('Expected the request to be rate limited.');
    } catch (RateLimitedException $exception) {
        expect($exception->resource)->toBe('core')
            ->and($exception->retryAt->getTimestamp())->toBe($reset);
    }

    Http::assertSentCount(1);

    // Later requests wait for the reset without asking GitHub.
    expect(fn () => app(GitHubClient::class)->get('repos/acme/app'))->toThrow(RateLimitedException::class);
    Http::assertSentCount(1);
});

it('waits as long as a secondary rate limit asks', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');

    Http::fake(['api.github.com/repos/acme/app' => Http::response(['message' => 'You have exceeded a secondary rate limit.'], 429, ['retry-after' => '90'])]);

    expect(fn () => app(GitHubClient::class)->get('repos/acme/app'))
        ->toThrow(fn (RateLimitedException $exception) => expect($exception->retryAt)->toEqual(now()->addSeconds(90)));

    Http::assertSentCount(1);
});

it('pauses before the remaining budget dips into the reserve', function () {
    config(['houston.github_rate_limit_reserve' => 50]);
    app(RateLimits::class)->recordAll(['graphql' => ['limit' => 5000, 'remaining' => 40, 'used' => 4960, 'reset' => now()->addMinutes(5)->getTimestamp()]]);
    Http::fake();

    expect(fn () => app(GitHubClient::class)->graphql('query { viewer { login } }'))->toThrow(RateLimitedException::class);

    Http::assertNothingSent();
});

it('goes ahead once the rate limit window has reset', function () {
    app(RateLimits::class)->recordAll(['core' => ['limit' => 5000, 'remaining' => 0, 'used' => 5000, 'reset' => now()->subMinute()->getTimestamp()]]);
    Http::fake(['api.github.com/repos/acme/app' => Http::response(['full_name' => 'acme/app'])]);

    expect(app(GitHubClient::class)->get('repos/acme/app'))->toBe(['full_name' => 'acme/app']);
});

it('treats an exhausted GraphQL point budget as a rate limit', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');

    Http::fake(['api.github.com/graphql' => Http::response(
        ['errors' => [['type' => 'RATE_LIMITED', 'message' => 'API rate limit exceeded']]],
        200,
        ['x-ratelimit-resource' => 'graphql', 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => '0', 'x-ratelimit-used' => '5000', 'x-ratelimit-reset' => (string) now()->addMinutes(20)->getTimestamp()],
    )]);

    expect(fn () => app(GitHubClient::class)->graphql('query { viewer { login } }'))
        ->toThrow(fn (RateLimitedException $exception) => expect($exception->retryAt)->toEqual(now()->addMinutes(20)));
});

/**
 * A response carrying the rate limit headers GitHub sends.
 */
function rateLimitedResponse(string $resource, int $remaining, int $reset): Response
{
    return new Response(new Psr7Response(200, [
        'x-ratelimit-resource' => $resource, 'x-ratelimit-limit' => '5000', 'x-ratelimit-remaining' => (string) $remaining,
        'x-ratelimit-used' => (string) (5000 - $remaining), 'x-ratelimit-reset' => (string) $reset,
    ]));
}

it('trusts response headers over the rate limit endpoint until the window resets', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $limits = app(RateLimits::class);

    $limits->record(rateLimitedResponse('graphql', 4123, now()->addMinutes(21)->getTimestamp()));
    $limits->recordAll(['graphql' => ['limit' => 5000, 'remaining' => 4794, 'used' => 206, 'reset' => now()->addMinutes(23)->getTimestamp()]]);

    expect($limits->all()['graphql']['remaining'])->toBe(4123);

    CarbonImmutable::setTestNow(now()->addMinutes(22));
    $limits->recordAll(['graphql' => ['limit' => 5000, 'remaining' => 4794, 'used' => 206, 'reset' => now()->addMinute()->getTimestamp()]]);

    expect($limits->all()['graphql']['remaining'])->toBe(4794);
});

it('ignores rate limit readings that arrive out of order', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $limits = app(RateLimits::class);
    $reset = now()->addMinutes(30)->getTimestamp();

    $limits->record(rateLimitedResponse('core', 4000, $reset));
    $limits->record(rateLimitedResponse('core', 4010, $reset));

    expect($limits->all()['core']['remaining'])->toBe(4000);

    $limits->record(rateLimitedResponse('core', 4999, now()->addMinutes(90)->getTimestamp()));

    expect($limits->all()['core']['remaining'])->toBe(4999);
});

it('keeps the known budget while a secondary rate limit pauses requests', function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $limits = app(RateLimits::class);

    $limits->record(rateLimitedResponse('core', 4500, now()->addMinutes(30)->getTimestamp()));
    $limits->block('core', now()->addSeconds(90));
    $limits->record(rateLimitedResponse('core', 4499, now()->addMinutes(30)->getTimestamp()));

    expect($limits->all()['core']['remaining'])->toBe(4499)
        ->and($limits->blockedUntil('core'))->toEqual(now()->addSeconds(90));

    CarbonImmutable::setTestNow(now()->addSeconds(91));

    expect($limits->blockedUntil('core'))->toBeNull();
});

it('updates rate limits stored before readings recorded where they came from', function () {
    Cache::forever('houston:github:rate-limits', ['graphql' => ['limit' => 5000, 'remaining' => 4800, 'used' => 200, 'reset' => now()->addMinutes(20)->getTimestamp(), 'observed' => now()->getTimestamp()]]);

    app(RateLimits::class)->recordAll(['graphql' => ['limit' => 5000, 'remaining' => 4700, 'used' => 300, 'reset' => now()->addMinutes(20)->getTimestamp()]]);

    expect(app(RateLimits::class)->all()['graphql']['remaining'])->toBe(4700);
});
