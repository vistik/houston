<?php

namespace App\GitHub;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The most recently seen GitHub rate limits per API resource (core, graphql, search, …), shared by every
 * worker so a sync stops before it runs into a limit another sync already used up.
 *
 * Readings arrive out of order from concurrent requests and workers, and the `rate_limit` endpoint
 * disagrees with the headers on GraphQL responses (it reports a different point budget and reset).
 * So within a window the headers win over the endpoint, and the remaining budget only ever goes down.
 *
 * @phpstan-type Limit array{limit: int, remaining: int, used: int, reset: int, observed: int, source?: string|null, blocked_until?: int}
 */
class RateLimits
{
    private const string CacheKey = 'houston:github:rate-limits';

    /**
     * When GitHub asks to slow down without saying for how long.
     */
    private const int DefaultBackoffSeconds = 60;

    /**
     * Remember the limits a response reports in its headers.
     */
    public function record(Response $response): void
    {
        $resource = $response->header('x-ratelimit-resource');

        if ($resource === '' || $response->header('x-ratelimit-limit') === '') {
            return;
        }

        $this->put($resource, [
            'limit' => (int) $response->header('x-ratelimit-limit'),
            'remaining' => (int) $response->header('x-ratelimit-remaining'),
            'used' => (int) $response->header('x-ratelimit-used'),
            'reset' => (int) $response->header('x-ratelimit-reset'),
        ], RateLimitSource::Response);
    }

    /**
     * Remember the limits reported by the `rate_limit` endpoint.
     *
     * @param  array<string, mixed>  $resources
     */
    public function recordAll(array $resources): void
    {
        foreach ($resources as $resource => $limits) {
            if (is_array($limits) && isset($limits['limit'], $limits['remaining'], $limits['reset'])) {
                $this->put((string) $resource, [
                    'limit' => (int) $limits['limit'],
                    'remaining' => (int) $limits['remaining'],
                    'used' => (int) ($limits['used'] ?? $limits['limit'] - $limits['remaining']),
                    'reset' => (int) $limits['reset'],
                ], RateLimitSource::Endpoint);
            }
        }
    }

    /**
     * Determine until when a rate limited response blocks further requests, or null when it was not rate limited.
     */
    public function retryAt(Response $response): ?CarbonImmutable
    {
        if (! in_array($response->status(), [403, 429], true)) {
            return null;
        }

        if (is_numeric($retryAfter = $response->header('retry-after'))) {
            return CarbonImmutable::now()->addSeconds((int) $retryAfter);
        }

        if ($response->header('x-ratelimit-remaining') === '0' && is_numeric($reset = $response->header('x-ratelimit-reset'))) {
            return CarbonImmutable::createFromTimestamp((int) $reset);
        }

        // Secondary limits only say so in the message.
        if (str_contains(strtolower((string) $response->json('message')), 'rate limit')) {
            return CarbonImmutable::now()->addSeconds(self::DefaultBackoffSeconds);
        }

        return null;
    }

    /**
     * Note that GitHub refused a resource until the given time, e.g. after a secondary limit. The budget
     * itself is left alone, since a secondary limit says nothing about it.
     */
    public function block(string $resource, CarbonImmutable $until): void
    {
        $this->update(function (array $limits) use ($resource, $until): array {
            $known = $limits[$resource] ?? ['limit' => 0, 'remaining' => 0, 'used' => 0, 'reset' => $until->getTimestamp(), 'source' => null];

            $limits[$resource] = [
                ...$known,
                'blocked_until' => max($known['blocked_until'] ?? 0, $until->getTimestamp()),
                'observed' => CarbonImmutable::now()->getTimestamp(),
            ];

            return $limits;
        });
    }

    /**
     * Until when requests to a resource should wait, after GitHub refused one or to keep the configured
     * reserve for everything else using the same token. Null when requests can go ahead.
     *
     * @param  int|null  $reserve  The budget to keep, which defaults to the configured reserve.
     */
    public function blockedUntil(string $resource, ?int $reserve = null): ?CarbonImmutable
    {
        $limits = $this->all()[$resource] ?? null;
        $now = CarbonImmutable::now()->getTimestamp();

        if ($limits === null) {
            return null;
        }

        $blockedUntil = $limits['blocked_until'] ?? 0;

        $until = max(
            $blockedUntil > $now ? $blockedUntil : 0,
            $limits['reset'] > $now && $limits['remaining'] <= ($reserve ?? config()->integer('houston.github_rate_limit_reserve')) ? $limits['reset'] : 0,
        );

        return $until > 0 ? CarbonImmutable::createFromTimestamp($until) : null;
    }

    /**
     * @return array<string, Limit>
     */
    public function all(): array
    {
        $limits = Cache::get(self::CacheKey);

        return is_array($limits) ? $limits : [];
    }

    /**
     * When the limits were last seen, or null when they never were.
     */
    public function observedAt(): ?CarbonImmutable
    {
        $observed = collect($this->all())->max('observed');

        return $observed === null ? null : CarbonImmutable::createFromTimestamp($observed);
    }

    /**
     * @param  array{limit: int, remaining: int, used: int, reset: int}  $reading
     */
    private function put(string $resource, array $reading, RateLimitSource $source): void
    {
        $this->update(function (array $limits) use ($resource, $reading, $source): array {
            $known = $limits[$resource] ?? null;

            if ($known !== null && ! $this->supersedes($reading, $source, $known)) {
                return $limits;
            }

            $limits[$resource] = [
                ...$reading,
                'source' => $source->value,
                'observed' => CarbonImmutable::now()->getTimestamp(),
                ...(isset($known['blocked_until']) ? ['blocked_until' => $known['blocked_until']] : []),
            ];

            return $limits;
        });
    }

    /**
     * Whether a new reading is more current than what is known.
     *
     * @param  array{limit: int, remaining: int, used: int, reset: int}  $reading
     * @param  array{remaining: int, reset: int, source?: string|null}  $known
     */
    private function supersedes(array $reading, RateLimitSource $source, array $known): bool
    {
        if ($known['reset'] <= CarbonImmutable::now()->getTimestamp() || ($known['source'] ?? null) !== RateLimitSource::Response->value) {
            return true;
        }

        // Within a live window the headers of real responses are what GitHub actually enforces.
        if ($source === RateLimitSource::Endpoint) {
            return false;
        }

        // A later window, or further into the same one. Responses from concurrent requests arrive in any order.
        return $reading['reset'] > $known['reset']
            || ($reading['reset'] === $known['reset'] && $reading['remaining'] <= $known['remaining']);
    }

    /**
     * Change the shared limits under a lock, so concurrent workers don't undo each other's readings.
     *
     * @param  callable(array<string, Limit>): array<string, Limit>  $change
     */
    private function update(callable $change): void
    {
        try {
            Cache::lock(self::CacheKey.':lock', 5)->block(3, fn () => Cache::forever(self::CacheKey, $change($this->all())));
        } catch (LockTimeoutException) {
            // Only bookkeeping: the next response reports the limits again.
        }
    }
}
