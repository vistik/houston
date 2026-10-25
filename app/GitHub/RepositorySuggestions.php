<?php

namespace App\GitHub;

use App\Models\Repository;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Suggests GitHub repositories to watch as someone types.
 *
 * Most repositories people watch are ones the token can already see, private ones included, so those come
 * first: a single `/user/repos` listing, cached and filtered locally, costs one core request every few
 * minutes however much is typed. Typing `owner/` lists that owner's repositories, again from the core budget.
 * Only when those don't fill the list does the search API fill in public repositories from elsewhere. It
 * allows just 30 requests a minute, so it is only asked for longer terms and its results are cached too.
 *
 * Every source fails on its own: when GitHub errors or is rate limited the others still answer, and the
 * input keeps working as plain text without any.
 *
 * @phpstan-type Suggestion array{fullName: string, owner: string, name: string, avatarUrl: string|null, private: bool, stars: int|null, description: string|null}
 * @phpstan-type WatchedSuggestion array{fullName: string, owner: string, name: string, avatarUrl: string|null, private: bool, stars: int|null, description: string|null, watched: bool}
 */
class RepositorySuggestions
{
    private const int Limit = 8;

    private const int CacheSeconds = 300;

    /**
     * The search API needs a few characters before its results mean anything.
     */
    private const int MinimumSearchLength = 3;

    /**
     * How many repositories a listing returns at most, which tells a complete listing from a cut off one.
     */
    private const int PageSize = 100;

    private const string CacheKey = 'houston:github:suggestions';

    private GitHubClient $github;

    /**
     * Whether one of the sources failed, so the suggestions may be missing some.
     */
    private bool $incomplete = false;

    public function __construct(GitHubClient $github)
    {
        $this->github = $github->impatient();
    }

    /**
     * @return array{suggestions: list<WatchedSuggestion>, complete: bool}
     */
    public function for(string $input): array
    {
        $this->incomplete = false;

        $input = trim(preg_replace('~^(?:https?://)?(?:www\.)?github\.com/~i', '', trim($input)) ?? '');

        $suggestions = match (true) {
            (bool) preg_match('~^(?<owner>[A-Za-z0-9-]+)/(?<name>[A-Za-z0-9_.-]*)$~', $input, $matches) => $this->forOwner($matches['owner'], $matches['name']),
            (bool) preg_match('~^[A-Za-z0-9_.-]*$~', $input) => $this->forTerm($input),
            default => collect(),
        };

        return [
            'suggestions' => array_values($this->markWatched($suggestions->take(self::Limit))->all()),
            'complete' => ! $this->incomplete,
        ];
    }

    /**
     * @return Collection<int, Suggestion>
     *
     * @phpstan-impure Marks the suggestions incomplete when a source fails.
     */
    private function forOwner(string $owner, string $name): Collection
    {
        $owned = fn (array $repository): bool => strcasecmp($repository['owner'], $owner) === 0;

        $listing = $this->attempt(fn (): array => $this->ownerRepositories($owner));

        $suggestions = $this->rank(
            collect($this->attempt(fn (): array => $this->accessibleRepositories()))->filter($owned)->merge($listing),
            $name,
            fn (array $repository): string => $repository['name'],
        );

        // A listing only holds the most recently pushed repositories, so a busy owner's older ones need a search.
        if ($name !== '' && $suggestions->count() < self::Limit && count($listing) >= self::PageSize) {
            $suggestions = $suggestions->merge($this->attempt(fn (): array => $this->searchRepositories("{$name} in:name user:{$owner}")));
        }

        return $suggestions->unique(fn (array $repository): string => strtolower($repository['fullName']));
    }

    /**
     * @return Collection<int, Suggestion>
     *
     * @phpstan-impure Marks the suggestions incomplete when a source fails.
     */
    private function forTerm(string $term): Collection
    {
        $suggestions = $this->rank(
            collect($this->attempt(fn (): array => $this->accessibleRepositories())),
            $term,
            fn (array $repository): string => $repository['fullName'],
        );

        if (strlen($term) >= self::MinimumSearchLength && $suggestions->count() < self::Limit) {
            $suggestions = $suggestions->merge($this->attempt(fn (): array => $this->searchRepositories("{$term} in:name")));
        }

        return $suggestions->unique(fn (array $repository): string => strtolower($repository['fullName']));
    }

    /**
     * Keep the repositories whose name contains the term, those whose name or owner starts with it first.
     * Otherwise the order of the source stands, which puts the most recently pushed first.
     *
     * @param  Collection<int, Suggestion>  $repositories
     * @param  callable(Suggestion): string  $haystack
     * @return Collection<int, Suggestion>
     */
    private function rank(Collection $repositories, string $term, callable $haystack): Collection
    {
        if ($term === '') {
            return $repositories->values();
        }

        $term = strtolower($term);

        return $repositories
            ->filter(fn (array $repository): bool => str_contains(strtolower($haystack($repository)), $term))
            ->sortBy(fn (array $repository): int => str_starts_with(strtolower($repository['name']), $term) || str_starts_with(strtolower($repository['owner']), $term) ? 0 : 1)
            ->values();
    }

    /**
     * The repositories the token can access, private ones included.
     *
     * @return list<Suggestion>
     */
    private function accessibleRepositories(): array
    {
        return Cache::remember(self::CacheKey.':accessible', self::CacheSeconds, fn (): array => $this->suggestions(
            $this->github->get('user/repos', ['per_page' => self::PageSize, 'sort' => 'pushed', 'affiliation' => 'owner,collaborator,organization_member']),
        ));
    }

    /**
     * @return list<Suggestion>
     */
    private function ownerRepositories(string $owner): array
    {
        return Cache::remember(self::CacheKey.':owner:'.strtolower($owner), self::CacheSeconds, function () use ($owner): array {
            try {
                return $this->suggestions($this->github->get("users/{$owner}/repos", ['per_page' => self::PageSize, 'sort' => 'pushed']));
            } catch (RequestException $exception) {
                // Half typed owners don't exist, and won't for the next few keystrokes either.
                if ($exception->response->notFound()) {
                    return [];
                }

                throw $exception;
            }
        });
    }

    /**
     * @return list<Suggestion>
     */
    private function searchRepositories(string $query): array
    {
        return Cache::remember(self::CacheKey.':search:'.md5(strtolower($query)), self::CacheSeconds, fn (): array => $this->suggestions(
            $this->github->search('repositories', ['q' => $query, 'per_page' => self::Limit])['items'] ?? [],
        ));
    }

    /**
     * Run a source, and treat it as empty when GitHub fails, is rate limited or there is no token.
     *
     * @param  callable(): list<Suggestion>  $source
     * @return list<Suggestion>
     */
    private function attempt(callable $source): array
    {
        try {
            return $source();
        } catch (HttpClientException|RuntimeException) {
            $this->incomplete = true;

            return [];
        }
    }

    /**
     * @param  array<array-key, mixed>  $repositories
     * @return list<Suggestion>
     */
    private function suggestions(array $repositories): array
    {
        return array_values(collect($repositories)
            ->filter(fn (mixed $repository): bool => is_array($repository) && isset($repository['full_name'], $repository['name'], $repository['owner']['login']))
            ->map(fn (array $repository): array => [
                'fullName' => (string) $repository['full_name'],
                'owner' => (string) $repository['owner']['login'],
                'name' => (string) $repository['name'],
                'avatarUrl' => is_string($repository['owner']['avatar_url'] ?? null) ? $repository['owner']['avatar_url'] : null,
                'private' => (bool) ($repository['private'] ?? false),
                'stars' => isset($repository['stargazers_count']) ? (int) $repository['stargazers_count'] : null,
                'description' => is_string($repository['description'] ?? null) ? $repository['description'] : null,
            ])
            ->all());
    }

    /**
     * @param  Collection<int, Suggestion>  $suggestions
     * @return Collection<int, WatchedSuggestion>
     */
    private function markWatched(Collection $suggestions): Collection
    {
        $watched = Repository::query()
            ->get(['owner', 'name'])
            ->map(fn (Repository $repository): string => strtolower($repository->fullName()))
            ->flip();

        return $suggestions->map(fn (array $repository): array => [
            ...$repository,
            'watched' => $watched->has(strtolower($repository['fullName'])),
        ]);
    }
}
