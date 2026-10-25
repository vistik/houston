<?php

namespace App\Settings;

use App\Metrics\CiFailureRateScope;
use App\Metrics\Granularity;
use App\Metrics\Period;
use App\Models\Setting;
use DateTimeZone;

/**
 * The period, grouping and whether to chart the current bucket that reports open on when the URL
 * doesn't pick them, which workflow runs the CI failure rate counts, and the timezone every report
 * is shown in.
 */
readonly class ReportDefaults
{
    private const string Key = 'report_defaults';

    /**
     * @param  int|null  $days  One of Period::AllowedDays, or null for all of the cached history.
     * @param  Granularity  $granularity  How charts group the period.
     * @param  bool  $withoutCurrentBucket  Whether charts leave out the bucket that is still filling up.
     * @param  CiFailureRateScope  $ciFailureRateScope  Which workflow runs the CI failure rate counts.
     * @param  string  $timezone  Where days start and end, for charts, custom ranges and working time.
     */
    public function __construct(
        public ?int $days = 30,
        public Granularity $granularity = Granularity::Day,
        public bool $withoutCurrentBucket = false,
        public CiFailureRateScope $ciFailureRateScope = CiFailureRateScope::DefaultBranch,
        public string $timezone = 'UTC',
    ) {}

    /**
     * The saved defaults, read once per request or queued job; see AppServiceProvider.
     */
    public static function current(): self
    {
        return app(self::class);
    }

    /**
     * Read the saved defaults, falling back for anything missing or no longer valid.
     */
    public static function load(): self
    {
        $value = Setting::get(self::Key, []);

        return new self(
            array_key_exists('days', $value) && ($value['days'] === null || in_array($value['days'], Period::AllowedDays, true)) ? $value['days'] : 30,
            Granularity::tryFrom((string) ($value['granularity'] ?? '')) ?? Granularity::Day,
            (bool) ($value['withoutCurrentBucket'] ?? false),
            CiFailureRateScope::tryFrom((string) ($value['ciFailureRateScope'] ?? '')) ?? CiFailureRateScope::DefaultBranch,
            self::validTimezone($value['timezone'] ?? null) ?? 'UTC',
        );
    }

    /**
     * The timezone reports are shown in: the one picked in the settings, or UTC until one is picked.
     */
    public static function timezone(): string
    {
        return self::current()->timezone;
    }

    public function save(): void
    {
        Setting::put(self::Key, $this->toArray());

        // Read them again next time, rather than the copy from before they changed.
        app()->forgetInstance(self::class);
    }

    /**
     * The timezone, when it is one of those to pick from, or null.
     */
    public static function validTimezone(mixed $timezone): ?string
    {
        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : null;
    }

    /**
     * @return array{days: int|null, granularity: string, withoutCurrentBucket: bool, ciFailureRateScope: string, timezone: string}
     */
    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'granularity' => $this->granularity->value,
            'withoutCurrentBucket' => $this->withoutCurrentBucket,
            'ciFailureRateScope' => $this->ciFailureRateScope->value,
            'timezone' => $this->timezone,
        ];
    }
}
