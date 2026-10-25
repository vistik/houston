<?php

namespace App\GitHub;

/**
 * Where a rate limit reading came from. Within a window the headers of real responses win, since the
 * `rate_limit` endpoint disagrees with them on GraphQL.
 */
enum RateLimitSource: string
{
    /** The headers of an API response. */
    case Response = 'response';

    /** The `rate_limit` endpoint. */
    case Endpoint = 'endpoint';
}
