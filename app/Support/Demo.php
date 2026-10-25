<?php

namespace App\Support;

use Closure;

/**
 * The public demo, turned on by `houston.demo`. Anyone may look around and nothing they do changes what the
 * next visitor sees. The scheduler keeps syncing the watched repositories, but no visitor's request ever
 * talks to GitHub, so they can't spend its rate limit or reach its token.
 */
final class Demo
{
    public static function enabled(): bool
    {
        return (bool) config('houston.demo');
    }

    /**
     * Whether this runs for a demo visitor. Commands and jobs have no visitor, so they work as usual.
     */
    public static function visiting(): bool
    {
        return self::enabled() && request()->hasSession();
    }

    /**
     * The value this visitor picked for a preference in the demo, or the saved one.
     */
    public static function preference(string $key, mixed $saved): mixed
    {
        return self::visiting() ? request()->session()->get("demo.{$key}", $saved) : $saved;
    }

    /**
     * Save a preference for everyone, or in the demo only for this visitor.
     */
    public static function savePreference(string $key, mixed $value, Closure $save): void
    {
        if (self::visiting()) {
            request()->session()->put("demo.{$key}", $value);

            return;
        }

        $save();
    }
}
