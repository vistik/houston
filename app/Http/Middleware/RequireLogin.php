<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends guests to the login page while `houston.auth` is on, and lets everyone through while it is off.
 * Until the login has been created there is nothing to log in with, so guests get the install guide instead.
 */
class RequireLogin
{
    /**
     * Whether the login is on but `php artisan houston:install` hasn't created it yet.
     */
    public static function awaitsLogin(): bool
    {
        return config('houston.auth') && User::query()->doesntExist();
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('houston.auth') && $request->user() === null) {
            if (! $request->expectsJson() && self::awaitsLogin()) {
                return to_route('landing.install');
            }

            throw new AuthenticationException(redirectTo: route('login'));
        }

        return $next($request);
    }
}
