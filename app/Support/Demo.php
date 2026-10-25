<?php

namespace App\Support;

use Closure;

/**
 * The public demo, turned on by `houston.demo`. Anyone may look around, nothing they do changes what the
 * next visitor sees, and Houston never talks to GitHub, so there is no token to leak.
 */
final class Demo
{
    public static function enabled(): bool
    {
        return (bool) config('houston.demo');
    }

    /**
     * The value this visitor picked for a preference in the demo, or the saved one.
     */
    public static function preference(string $key, mixed $saved): mixed
    {
        return self::forVisitor() ? request()->session()->get("demo.{$key}", $saved) : $saved;
    }

    /**
     * Save a preference for everyone, or in the demo only for this visitor.
     */
    public static function savePreference(string $key, mixed $value, Closure $save): void
    {
        if (self::forVisitor()) {
            request()->session()->put("demo.{$key}", $value);

            return;
        }

        $save();
    }

    /**
     * Commands and jobs have no visitor, so they keep reading and writing the saved preferences.
     */
    private static function forVisitor(): bool
    {
        return self::enabled() && request()->hasSession();
    }
}
