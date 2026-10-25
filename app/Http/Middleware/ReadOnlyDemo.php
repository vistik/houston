<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the public demo read-only. Visitors can still switch layouts and views, which the demo keeps in
 * their own session, but nothing else they send is saved, and the pages that change Houston or show its
 * internals are hidden.
 */
class ReadOnlyDemo
{
    /**
     * Requests that only change how things look, kept per visitor by `Demo::savePreference()`.
     */
    private const array Preferences = [
        'home.pins',
        'overview-layouts.update',
        'repositories.view',
        'repositories.card-view',
        'teams.card-view',
    ];

    /**
     * Settings change Houston for everyone and the log shows its internals; suggestions would ask GitHub.
     */
    private const array Hidden = ['settings', 'settings.*', 'repositories.suggestions'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Demo::enabled()) {
            return $next($request);
        }

        abort_if($request->routeIs(...self::Hidden), 404);
        abort_unless($request->isMethodSafe() || $request->routeIs('login.store', 'logout', ...self::Preferences), 403, 'The demo is read-only.');

        return $next($request);
    }
}
