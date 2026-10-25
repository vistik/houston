<?php

namespace App\Metrics;

use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A reporting window of whole days, either the last few days up to now, all of the cached history or a
 * custom range, together with the equally long window right before it. Days start at midnight in the
 * timezone the period is shown in, while its moments are in the app's timezone, as queries expect them.
 *
 * "The last 30 days" are today and the 29 days before it, so each charted bucket is a calendar day and
 * the buckets add up to the period's totals. Today is still under way, so the previous window is only
 * as long as the time that has passed, which keeps comparing the two fair.
 */
readonly class Period
{
    /**
     * @var list<int>
     */
    public const array AllowedDays = [7, 14, 30, 45, 90, 180, 365];

    /**
     * The timezone the period is shown in, where its days and charted buckets start at midnight.
     */
    public string $timezone;

    /**
     * Midnight on the first day, where the first charted bucket starts too.
     */
    public CarbonImmutable $from;

    /**
     * Midnight after the last day of a custom range, or now for a period that runs up to today.
     */
    public CarbonImmutable $to;

    public CarbonImmutable $previousFrom;

    /**
     * Where the charts start: the start of the period, or as many whole buckets back into the window
     * before it as fit, when that is charted too. Always midnight, like the start of the period.
     */
    public CarbonImmutable $chartFrom;

    /**
     * Where the last charted bucket ends.
     */
    public CarbonImmutable $chartedUntil;

    /**
     * The start of each charted bucket, in the timezone the period is shown in, worked out once since
     * reports ask for them per pull request.
     *
     * @var list<CarbonImmutable>
     */
    private array $buckets;

    /**
     * The same starts as unix timestamps, for finding a moment's bucket cheaply.
     *
     * @var list<int>
     */
    private array $bucketTimestamps;

    /**
     * @param  int  $days  How many days the period covers, today included when it runs up to now.
     * @param  CarbonImmutable  $to  Where the period ends, in the timezone it is shown in: now, or midnight after its last day.
     * @param  Granularity|null  $granularity  How to chart the period, or null to pick from the period's length.
     * @param  bool  $allTime  Whether the period reaches back as far as the cached history does.
     * @param  bool  $withoutCurrentBucket  Whether charts leave out the last bucket, which is still filling up.
     * @param  bool  $chartsPrevious  Whether charts reach back over the window before the period too, to compare the two side by side.
     */
    public function __construct(
        public int $days,
        CarbonImmutable $to,
        public bool $custom = false,
        public ?Granularity $granularity = null,
        public bool $allTime = false,
        public bool $withoutCurrentBucket = false,
        public bool $chartsPrevious = false,
    ) {
        // Work in the period's own timezone, so subtracting days and finding midnight follow its clock.
        $endOfLastDay = $to->startOfDay()->eq($to) ? $to : $to->startOfDay()->addDay();
        $from = $endOfLastDay->subDays($days);
        // Whole buckets back from the start of the period, so none straddles it.
        $chartFrom = $chartsPrevious ? $from->subDays(intdiv($days, $this->bucketDays()) * $this->bucketDays()) : $from;
        $buckets = $this->startsOfBuckets($chartFrom, $to);
        $app = date_default_timezone_get();

        $this->timezone = $to->getTimezone()->getName();
        $this->from = $from->setTimezone($app);
        $this->to = $to->setTimezone($app);
        $this->previousFrom = $from->subSeconds($to->getTimestamp() - $from->getTimestamp())->setTimezone($app);
        $this->chartFrom = $chartFrom->setTimezone($app);
        $this->buckets = $buckets;
        $this->bucketTimestamps = array_map(fn (CarbonImmutable $start): int => $start->getTimestamp(), $buckets);
        $this->chartedUntil = ($buckets === [] ? $chartFrom : end($buckets)->addDays($this->bucketDays())->min($to))->setTimezone($app);
    }

    /**
     * @param  string  $timezone  The timezone to show it in, usually ReportDefaults::timezone().
     */
    public static function lastDays(int $days, string $timezone): self
    {
        return new self(in_array($days, self::AllowedDays, true) ? $days : 30, CarbonImmutable::now($timezone));
    }

    /**
     * Everything the cached history covers, up to now.
     *
     * @param  string  $timezone  The timezone to show it in, usually ReportDefaults::timezone().
     * @param  int|null  $lookbackDays  How far back the cached history reaches, defaulting to the configured lookback.
     */
    public static function allTime(string $timezone, ?int $lookbackDays = null): self
    {
        return new self($lookbackDays ?? config()->integer('houston.lookback_days'), CarbonImmutable::now($timezone), allTime: true);
    }

    /**
     * A custom range when the request has valid `from` and `to` dates, all of the cached history when
     * `days` is "all", otherwise the last `days` days. Whatever the request leaves out comes from the
     * report defaults. Given a day to start from, the period runs from that day through today instead,
     * whatever the request asks for, grouped as fits its length unless the request picks a grouping.
     *
     * @param  int|null  $lookbackDays  How far back the cached history reaches, defaulting to the configured lookback.
     */
    public static function fromRequest(Request $request, ?int $lookbackDays = null, ?ReportDefaults $defaults = null, ?CarbonImmutable $since = null): self
    {
        $defaults ??= ReportDefaults::current();
        $from = self::date($request->string('from')->value());
        $to = self::date($request->string('to')->value());
        $days = $request->filled('days') ? $request->string('days')->value() : ($defaults->days ?? 'all');

        $period = match (true) {
            $since !== null => self::between($since, CarbonImmutable::now($defaults->timezone), $defaults->timezone, $lookbackDays),
            $from !== null && $to !== null => self::between($from, $to, $defaults->timezone, $lookbackDays),
            $days === 'all' => self::allTime($defaults->timezone, $lookbackDays),
            default => self::lastDays((int) $days, $defaults->timezone),
        };

        return $period->groupedBy($request->filled('granularity') ? Granularity::tryFrom($request->string('granularity')->value()) : ($since === null ? $defaults->granularity : null));
    }

    /**
     * The same period, charted per day, week or month. Granularities that would leave fewer than two
     * buckets are ignored.
     */
    public function groupedBy(?Granularity $granularity): self
    {
        $fits = $granularity !== null && $this->days >= 2 * $granularity->days();

        return new self($this->days, $this->local($this->to), $this->custom, $fits ? $granularity : null, $this->allTime, $this->withoutCurrentBucket, $this->chartsPrevious);
    }

    /**
     * The same period, with charts that stop before the last bucket so a day, week or month that is
     * still filling up doesn't read as a drop.
     */
    public function withoutCurrentBucket(bool $without = true): self
    {
        return new self($this->days, $this->local($this->to), $this->custom, $this->granularity, $this->allTime, $without, $this->chartsPrevious);
    }

    /**
     * The same period, with charts that start at the beginning of the window before it, so what came
     * after a change can be seen next to what came before.
     */
    public function chartingPrevious(bool $charting = true): self
    {
        return new self($this->days, $this->local($this->to), $this->custom, $this->granularity, $this->allTime, $this->withoutCurrentBucket, $charting);
    }

    /**
     * Whole days from `$from` through `$to`, kept within the cached history and not past today. Only
     * the dates count, so the days start and end at midnight in the timezone the period is shown in.
     *
     * @param  string  $timezone  The timezone to show it in, usually ReportDefaults::timezone().
     * @param  int|null  $lookbackDays  How far back the cached history reaches, defaulting to the configured lookback.
     */
    public static function between(CarbonImmutable $from, CarbonImmutable $to, string $timezone, ?int $lookbackDays = null): self
    {
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $today = CarbonImmutable::today($timezone);
        $from = CarbonImmutable::parse($from->toDateString(), $timezone);
        $to = CarbonImmutable::parse($to->toDateString(), $timezone);
        $from = $from->max($today->subDays($lookbackDays ?? config()->integer('houston.lookback_days')))->min($today);
        $to = $to->min($today)->max($from);

        return new self((int) round($from->diffInDays($to)) + 1, $to->addDay(), custom: true);
    }

    /**
     * The query string that reproduces this period.
     *
     * @return array{days: int, from: string|null, to: string|null, all: bool}
     */
    public function filters(): array
    {
        return [
            'days' => $this->days,
            'from' => $this->custom ? $this->local($this->from)->toDateString() : null,
            'to' => $this->custom ? $this->local($this->to)->subDay()->toDateString() : null,
            'all' => $this->allTime,
        ];
    }

    /**
     * Identifies the period in cache keys by its length or range, how it is charted and whether the
     * current bucket is left out. Rolling periods move with now, so their length is enough. The
     * granularity is the one charted, so picking the one a period would default to shares its key.
     */
    public function key(): string
    {
        $range = $this->custom ? "{$this->local($this->from)->toDateString()}..{$this->local($this->to)->toDateString()}" : (string) $this->days;

        return "{$range}@{$this->bucketGranularity()->value}".($this->withoutCurrentBucket ? '-complete' : '').($this->chartsPrevious ? '-before' : '');
    }

    /**
     * The moment in the timezone the period is shown in.
     */
    public function local(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone($this->timezone);
    }

    public function contains(?CarbonImmutable $moment): bool
    {
        return $moment !== null && $moment->gte($this->from) && $moment->lt($this->to);
    }

    public function previousContains(?CarbonImmutable $moment): bool
    {
        return $moment !== null && $moment->gte($this->previousFrom) && $moment->lt($this->from);
    }

    /**
     * The chosen granularity, or daily buckets for short periods and weekly buckets for long ones.
     */
    public function bucketDays(): int
    {
        return $this->bucketGranularity()->days();
    }

    /**
     * How the charts group the period.
     */
    public function bucketGranularity(): Granularity
    {
        return $this->granularity ?? ($this->days > 30 ? Granularity::Week : Granularity::Day);
    }

    /**
     * Where each charted bucket starts. The last one is left out when asked to, as long as another
     * bucket remains.
     *
     * @return list<CarbonImmutable>
     */
    public function buckets(): array
    {
        return $this->buckets;
    }

    /**
     * Whether the moment falls within one of the charted buckets.
     */
    public function charts(?CarbonImmutable $moment): bool
    {
        return $moment !== null && $moment->gte($this->chartFrom) && $moment->lt($this->chartedUntil);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function startsOfBuckets(CarbonImmutable $chartFrom, CarbonImmutable $to): array
    {
        $size = $this->bucketDays();
        $buckets = [];

        // Adding days keeps each start at midnight, even across a daylight saving change.
        for ($start = $chartFrom; $start->lt($to); $start = $start->addDays($size)) {
            $buckets[] = $start;
        }

        if ($this->withoutCurrentBucket && count($buckets) > 1) {
            array_pop($buckets);
        }

        return $buckets;
    }

    /**
     * Which charted bucket a moment within them falls into.
     */
    public function bucketIndex(CarbonImmutable $moment): int
    {
        $timestamp = $moment->getTimestamp();
        $last = count($this->bucketTimestamps) - 1;

        // Days are 24 hours apart except across a daylight saving change, so the estimate is at
        // most one bucket off.
        $index = max(0, min($last, intdiv($timestamp - $this->chartFrom->getTimestamp(), $this->bucketDays() * 86400)));

        while ($index < $last && $timestamp >= $this->bucketTimestamps[$index + 1]) {
            $index++;
        }

        while ($index > 0 && $timestamp < $this->bucketTimestamps[$index]) {
            $index--;
        }

        return $index;
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        // Reject dates PHP would roll over, such as 2026-02-31.
        return $date?->toDateString() === $value ? $date : null;
    }
}
