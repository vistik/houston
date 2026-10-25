<?php

namespace App\Metrics;

use App\Settings\ReportDefaults;

/**
 * Which workflow runs the headline CI failure rate counts.
 */
enum CiFailureRateScope: string
{
    /** Runs on the default branch outside pull requests, so work in progress on other branches doesn't count. */
    case DefaultBranch = 'default_branch';

    /** Runs on every branch, pull requests included. */
    case AllBranches = 'all_branches';

    /**
     * The scope reports use, from the report defaults.
     */
    public static function current(): self
    {
        return ReportDefaults::current()->ciFailureRateScope;
    }

    /**
     * Which runs are counted, to finish a sentence in a metric's description.
     */
    public function runs(): string
    {
        return match ($this) {
            self::DefaultBranch => 'on the default branch outside pull requests',
            self::AllBranches => 'on any branch',
        };
    }
}
