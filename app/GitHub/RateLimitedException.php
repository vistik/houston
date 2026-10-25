<?php

namespace App\GitHub;

use Carbon\CarbonImmutable;

/**
 * GitHub refuses further requests to a resource until the rate limit resets.
 */
class RateLimitedException extends GitHubException
{
    public readonly CarbonImmutable $retryAt;

    /**
     * Thrown in a concurrent sync process, the exception is rebuilt in the parent from its JSON encoded
     * constructor arguments, so the retry time also arrives as a string.
     */
    public function __construct(public readonly string $resource, CarbonImmutable|string $retryAt)
    {
        $this->retryAt = CarbonImmutable::parse($retryAt);

        parent::__construct("GitHub {$resource} API rate limit reached until {$this->retryAt->toIso8601ZuluString()}.");
    }
}
