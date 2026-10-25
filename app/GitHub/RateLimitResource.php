<?php

namespace App\GitHub;

/**
 * The GitHub API rate limits Houston draws on, by the resource name GitHub reports them under. GitHub
 * has more, which RateLimits keeps track of by name.
 */
enum RateLimitResource: string
{
    case Core = 'core';

    case Graphql = 'graphql';

    case Search = 'search';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'REST API',
            self::Graphql => 'GraphQL API',
            self::Search => 'Search API',
        };
    }

    /**
     * How many requests to leave for everything else using the same token. Only autocomplete draws on the
     * search budget, which at 30 a minute is smaller than the reserve kept for the others.
     */
    public function reserve(): int
    {
        return match ($this) {
            self::Search => 0,
            default => config()->integer('houston.github_rate_limit_reserve'),
        };
    }
}
