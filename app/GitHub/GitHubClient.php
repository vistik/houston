<?php

namespace App\GitHub;

use App\Support\Demo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class GitHubClient
{
    /**
     * Whether requests wait out transient failures. Someone typing would rather see nothing than wait.
     */
    private bool $patient = true;

    public function __construct(private RateLimits $rateLimits, private ?string $token = null) {}

    /**
     * A copy of the client that gives up on the first failure and after a few seconds, for requests
     * someone is waiting on as they type.
     */
    public function impatient(): static
    {
        $client = clone $this;
        $client->patient = false;

        return $client;
    }

    /**
     * Run a GraphQL query and return its `data` payload.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RateLimitedException
     */
    public function graphql(string $query, array $variables = []): array
    {
        $this->ensureNotRateLimited(RateLimitResource::Graphql);

        $response = $this->inspect(RateLimitResource::Graphql, $this->request()->post('graphql', ['query' => $query, 'variables' => $variables]))->throw();

        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            // GraphQL reports an exhausted point budget as an error on an otherwise successful response.
            if (in_array('RATE_LIMITED', array_column($errors, 'type'), true)) {
                $this->rateLimited(RateLimitResource::Graphql->value, is_numeric($reset = $response->header('x-ratelimit-reset'))
                    ? CarbonImmutable::createFromTimestamp((int) $reset)
                    : CarbonImmutable::now()->addMinute());
            }

            throw new GitHubException(implode('; ', array_column($errors, 'message')));
        }

        return $response->json('data') ?? [];
    }

    /**
     * Run several GraphQL queries concurrently and return their `data` payloads, preserving order.
     *
     * Queries that fail or report errors inside the pool are retried one by one, which also surfaces
     * an exhausted point budget as a rate limit.
     *
     * @param  list<array{0: string, 1: array<string, mixed>}>  $queries  Pairs of query and variables.
     * @param  int<1, max>  $concurrency
     * @return list<array<string, mixed>>
     *
     * @throws RateLimitedException
     */
    public function graphqlMany(array $queries, int $concurrency = 6): array
    {
        $this->ensureNotRateLimited(RateLimitResource::Graphql);

        $responses = Http::pool(
            fn (Pool $pool): array => array_map(
                fn (array $query) => $this->configure($pool->asJson())->post('graphql', ['query' => $query[0], 'variables' => $query[1]]),
                $queries,
            ),
            concurrency: $concurrency,
        );

        return array_map(function (int $index) use ($responses, $queries): array {
            $response = $responses[$index] ?? null;

            if ($response instanceof Response) {
                $this->inspect(RateLimitResource::Graphql, $response);

                if ($response->successful() && empty($response->json('errors'))) {
                    return $response->json('data') ?? [];
                }
            }

            return $this->graphql(...$queries[$index]);
        }, array_keys($queries));
    }

    /**
     * Perform a GET request against the REST API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws RateLimitedException
     */
    public function get(string $path, array $query = []): array
    {
        $this->ensureNotRateLimited(RateLimitResource::Core);

        return $this->inspect(RateLimitResource::Core, $this->request()->get(ltrim($path, '/'), $query))->throw()->json() ?? [];
    }

    /**
     * Run a query against a search endpoint, e.g. `repositories`, which draws on the separate search budget.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws RateLimitedException
     */
    public function search(string $type, array $query): array
    {
        $this->ensureNotRateLimited(RateLimitResource::Search);

        return $this->inspect(RateLimitResource::Search, $this->request()->get("search/{$type}", $query))->throw()->json() ?? [];
    }

    /**
     * Fetch a file of a repository as it is, which also works for files too large to come back base64-encoded.
     *
     * @throws RateLimitedException
     */
    public function rawFile(string $repository, string $path): string
    {
        $this->ensureNotRateLimited(RateLimitResource::Core);

        return $this->inspect(RateLimitResource::Core, $this->request()->accept('application/vnd.github.raw+json')->get("repos/{$repository}/contents/".ltrim($path, '/')))
            ->throw()
            ->body();
    }

    /**
     * Fetch the current limits of every API resource. Checking them does not count against them.
     *
     * @return array<string, array{limit: int, remaining: int, used: int, reset: int, observed: int}>
     */
    public function rateLimits(): array
    {
        $this->rateLimits->recordAll($this->request()->get('rate_limit')->throw()->json('resources') ?? []);

        return $this->rateLimits->all();
    }

    /**
     * Perform several GET requests against the same REST endpoint concurrently, preserving order.
     *
     * Requests that fail inside the pool are retried one by one so a single hiccup does not fail a sync.
     *
     * @param  list<array<string, mixed>>  $queries
     * @param  int<1, max>  $concurrency
     * @return list<array<string, mixed>>
     *
     * @throws RateLimitedException
     */
    public function getMany(string $path, array $queries, int $concurrency = 6): array
    {
        $this->ensureNotRateLimited(RateLimitResource::Core);

        $responses = Http::pool(
            fn (Pool $pool): array => array_map(
                fn (array $query) => $this->configure($pool->asJson())->get(ltrim($path, '/'), $query),
                $queries,
            ),
            concurrency: $concurrency,
        );

        return array_map(function (int $index) use ($responses, $path, $queries): array {
            $response = $responses[$index] ?? null;

            if ($response instanceof Response) {
                $this->rateLimits->record($response);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }
            }

            return $this->get($path, $queries[$index]);
        }, array_keys($queries));
    }

    /**
     * @throws RateLimitedException
     */
    private function ensureNotRateLimited(RateLimitResource $resource): void
    {
        if (($until = $this->rateLimits->blockedUntil($resource->value, $resource->reserve())) !== null) {
            throw new RateLimitedException($resource->value, $until);
        }
    }

    /**
     * Record the limits a response reports, and stop when GitHub refused it for hitting one. The response
     * names the limit it counted against, which can be another than the one the request expected.
     *
     * @throws RateLimitedException
     */
    private function inspect(RateLimitResource $expected, Response $response): Response
    {
        $this->rateLimits->record($response);

        if (($retryAt = $this->rateLimits->retryAt($response)) !== null) {
            $this->rateLimited($response->header('x-ratelimit-resource') ?: $expected->value, $retryAt);
        }

        return $response;
    }

    /**
     * @throws RateLimitedException
     */
    private function rateLimited(string $resource, CarbonImmutable $until): never
    {
        $this->rateLimits->block($resource, $until);

        throw new RateLimitedException($resource, $until);
    }

    private function request(): PendingRequest
    {
        if (! $this->patient) {
            return $this->configure(Http::asJson())->timeout(5);
        }

        // Rate limits are left to the caller: retrying within seconds would not get past them.
        return $this->configure(Http::asJson())->retry(
            [1000, 3000, 8000],
            when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && ($exception->response->serverError()
                    || ($exception->response->tooManyRequests() && $this->rateLimits->retryAt($exception->response) === null))),
            throw: false,
        );
    }

    private function configure(PendingRequest $request): PendingRequest
    {
        return $request->baseUrl('https://api.github.com')
            ->withToken($this->token())
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->timeout(60);
    }

    /**
     * Resolve the API token from config, falling back to the GitHub CLI. Only the demo's syncs may use it.
     */
    private function token(): string
    {
        if (Demo::visiting()) {
            throw new RuntimeException('Houston does not talk to GitHub for demo visitors.');
        }

        $token = $this->token ?? config('houston.github_token');

        if (is_string($token) && $token !== '') {
            return $this->token = $token;
        }

        $token = once(function (): string {
            $gh = $this->githubCli();

            if ($gh === null) {
                return '';
            }

            $result = Process::run([$gh, 'auth', 'token']);

            return $result->successful() ? trim($result->output()) : '';
        });

        if ($token === '') {
            throw new RuntimeException('No GitHub token available. Set GITHUB_TOKEN or run `gh auth login`.');
        }

        return $this->token = $token;
    }

    /**
     * Locate the GitHub CLI. Processes started without a login shell, such as PHP-FPM, can have a minimal PATH, so also look
     * where Homebrew installs it.
     */
    private function githubCli(): ?string
    {
        return (new ExecutableFinder)->find('gh', null, ['/opt/homebrew/bin', '/usr/local/bin']);
    }
}
