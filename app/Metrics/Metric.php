<?php

namespace App\Metrics;

/**
 * One headline number of a velocity report: how it is calculated, and how to read it.
 */
interface Metric
{
    /**
     * The key the metric is reported under, e.g. "cycleTime".
     */
    public function key(): string;

    public function unit(): Unit;

    /**
     * Whether a higher or lower value is an improvement.
     */
    public function better(): Better;

    /**
     * How the metric is calculated, in plain words for the people reading the report.
     */
    public function description(): string;

    /**
     * The value over a window, or null when there is nothing to compute it from.
     */
    public function calculate(Window $window): float|int|null;
}
