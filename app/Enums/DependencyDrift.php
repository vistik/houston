<?php

namespace App\Enums;

/**
 * How far the installed version of a dependency is behind its latest stable release.
 */
enum DependencyDrift: string
{
    case Current = 'current';

    case Patch = 'patch';

    case Minor = 'minor';

    /** A new major version. */
    case Major = 'major';

    /** The installed or the latest version is not known, e.g. a branch or a private package. */
    case Unknown = 'unknown';

    public static function between(?string $installed, ?string $latest): self
    {
        $installed = self::parse($installed);
        $latest = self::parse($latest);

        if ($installed === null || $latest === null) {
            return self::Unknown;
        }

        if (version_compare(implode('.', $installed), implode('.', $latest), '>=')) {
            return self::Current;
        }

        return match (true) {
            $installed[0] !== $latest[0] => self::Major,
            $installed[1] !== $latest[1] => self::Minor,
            default => self::Patch,
        };
    }

    /**
     * Rate a version constraint by whether it lets in the latest release, for repositories without a lock
     * file, like libraries. Constraints take in the minor and patch releases of the versions they name, so
     * only a new major version counts against them, except on 0.x, where they stop at the next minor one.
     */
    public static function allowedBy(string $constraint, ?string $latest): self
    {
        $latest = self::parse($latest);

        if ($latest === null) {
            return self::Unknown;
        }

        $lines = array_map(self::highestLine(...), preg_split('/\s*\|\|?\s*/', trim($constraint)) ?: []);

        if (in_array(null, $lines, true)) {
            return self::Unknown;
        }

        foreach ($lines as $line) {
            if ($line === true || $latest[0] < $line[0] || ($latest[0] === $line[0] && ($line[0] > 0 || $latest[1] <= $line[1]))) {
                return self::Current;
            }
        }

        // Only a 0.x constraint can leave out a release of its own major version.
        foreach ($lines as $line) {
            if ($latest[0] === $line[0]) {
                return self::Minor;
            }
        }

        return self::Major;
    }

    public function isOutdated(): bool
    {
        return in_array($this, [self::Patch, self::Minor, self::Major], true);
    }

    /**
     * The newest release line one range of a constraint lets in: its major version and, for 0.x, its minor
     * one. True when it has no upper bound, and null when it names no version, like `dev-main`.
     *
     * @return array{int, int}|true|null
     */
    private static function highestLine(string $range): array|true|null
    {
        if (in_array(strtolower($range), ['', '*', 'x', 'latest'], true)) {
            return true;
        }

        // An upper bound such as `<3.0` stops short of the version it names.
        if (preg_match('/<(=?)\s*v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $range, $bound) === 1) {
            [$major, $minor, $patch] = [(int) $bound[2], (int) ($bound[3] ?? 0), (int) ($bound[4] ?? 0)];

            return match (true) {
                $bound[1] === '=' || $patch > 0 => [$major, $minor],
                $minor > 0 => [$major, $minor - 1],
                $major > 0 => [$major - 1, PHP_INT_MAX],
                default => null,
            };
        }

        if (str_contains($range, '>')) {
            return true;
        }

        if (preg_match_all('/v?(\d+)(?:\.(\d+))?/', $range, $versions, PREG_SET_ORDER) === 0) {
            return null;
        }

        $highest = [0, 0];

        foreach ($versions as $version) {
            $line = [(int) $version[1], (int) ($version[2] ?? 0)];
            $highest = $line > $highest ? $line : $highest;
        }

        return $highest;
    }

    /**
     * Read a release version such as `v1.2.3` or `1.2`. Branches (`dev-main`, `2.x-dev`) and pre-releases
     * are not releases to compare against.
     *
     * @return array{int, int, int}|null
     */
    private static function parse(?string $version): ?array
    {
        if ($version === null || preg_match('/^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.\d+)?$/i', trim($version), $parts) !== 1) {
            return null;
        }

        return [(int) $parts[1], (int) ($parts[2] ?? 0), (int) ($parts[3] ?? 0)];
    }
}
