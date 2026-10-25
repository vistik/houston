<?php

namespace App\Metrics;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Measures durations in working time: only configured weekdays, between the configured hours.
 */
readonly class WorkingTime
{
    private const array DayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    private DateTimeZone $timezone;

    /** @var array<int, true> */
    private array $days;

    private int $startSeconds;

    private int $endSeconds;

    /**
     * @param  list<int>  $days  ISO weekdays, 1 = Monday.
     */
    public function __construct(string $timezone, array $days, string $start, string $end)
    {
        $this->timezone = new DateTimeZone($timezone);
        $this->days = array_fill_keys(array_values(array_filter($days, fn (int $day): bool => $day >= 1 && $day <= 7)), true);
        $this->startSeconds = self::secondsOfDay($start);
        $this->endSeconds = max($this->startSeconds, self::secondsOfDay($end));
    }

    /**
     * The configured working days and hours, in the given timezone.
     */
    public static function in(string $timezone): self
    {
        /** @var array{days: list<int>, start: string, end: string} $config */
        $config = config('houston.working_time');

        return new self($timezone, $config['days'], $config['start'], $config['end']);
    }

    /**
     * Whether every moment counts, in which case durations are plain wall-clock time.
     */
    public function isCalendarTime(): bool
    {
        return count($this->days) === 7 && $this->startSeconds === 0 && $this->endSeconds === 86400;
    }

    /**
     * Working seconds between two moments.
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to <= $from) {
            return 0;
        }

        if ($this->isCalendarTime()) {
            return (int) $from->diffInSeconds($to);
        }

        // Reports call this tens of thousands of times, so it steps through days with plain
        // DateTimeImmutable, which is far cheaper than Carbon, in the configured timezone.
        $fromTimestamp = $from->getTimestamp();
        $toTimestamp = $to->getTimestamp();
        $day = (new DateTimeImmutable('@'.$fromTimestamp))->setTimezone($this->timezone)->setTime(0, 0);
        $total = 0;

        while (($dayTimestamp = $day->getTimestamp()) < $toTimestamp) {
            $weekday = (int) $day->format('N');

            // Skip whole weeks arithmetically so year-old pull requests don't cost a loop per day.
            if ($weekday === 1 && $dayTimestamp >= $fromTimestamp) {
                $weeks = intdiv($toTimestamp - $dayTimestamp, 7 * 86400);

                if ($weeks > 0) {
                    $total += $weeks * $this->weekSeconds();
                    $day = $day->modify("+{$weeks} weeks");

                    continue;
                }
            }

            $next = $day->modify('+1 day');

            if (isset($this->days[$weekday])) {
                $windowStart = $dayTimestamp + $this->startSeconds;
                $windowEnd = min($dayTimestamp + $this->endSeconds, $next->getTimestamp());

                $total += max(0, min($windowEnd, $toTimestamp) - max($windowStart, $fromTimestamp));
            }

            $day = $next;
        }

        return $total;
    }

    /**
     * Working seconds in a full week.
     */
    private function weekSeconds(): int
    {
        return count($this->days) * ($this->endSeconds - $this->startSeconds);
    }

    /**
     * A short human description, e.g. "Mon–Fri" or "Mon–Fri 09:00–17:00 (Europe/Copenhagen)".
     */
    public function label(): string
    {
        if ($this->isCalendarTime()) {
            return 'Calendar time';
        }

        $days = array_keys($this->days);
        sort($days);

        if ($days === []) {
            return 'No working days';
        }

        $first = $days[0];
        $last = $days[count($days) - 1];

        $dayLabel = $days === range($first, $last)
            ? self::DayNames[$first].'–'.self::DayNames[$last]
            : implode(', ', array_map(fn (int $day): string => self::DayNames[$day], $days));

        $hours = $this->startSeconds === 0 && $this->endSeconds === 86400
            ? ''
            : ' '.self::clock($this->startSeconds).'–'.self::clock($this->endSeconds);

        return "{$dayLabel}{$hours} ({$this->timezone->getName()})";
    }

    private static function secondsOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', array_pad(explode(':', $time, 2), 2, '0'));

        return min(86400, max(0, $hours * 3600 + $minutes * 60));
    }

    private static function clock(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
