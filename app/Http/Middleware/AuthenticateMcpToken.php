<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP endpoint with the shared token from `houston.mcp_token`, hiding it entirely when none is set.
 */
class AuthenticateMcpToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('houston.mcp_token');

        abort_if(blank($token), 404);
        abort_unless(is_string($request->bearerToken()) && hash_equals((string) $token, $request->bearerToken()), 401);

        return $next($request);
    }
}
