<?php

namespace App\Metrics;

/**
 * One chart of a velocity report's trends: what it plots per bucket, and how to read it.
 */
interface Trend
{
    /**
     * The key the trend is reported under, e.g. "throughput".
     */
    public function key(): string;

    public function title(): string;

    /**
     * What the chart shows, in a few words under its title.
     */
    public function summary(): string;

    /**
     * How each point is calculated, in plain words for the people reading the report.
     */
    public function description(): string;

    public function unit(): Unit;
}
