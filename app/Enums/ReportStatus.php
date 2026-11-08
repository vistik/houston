<?php

namespace App\Enums;

/**
 * Whether a cached report reflects the latest data and settings.
 */
enum ReportStatus: string
{
    case Current = 'current';

    /** A queued job is building it. Any report shown meanwhile was built from older data or settings. */
    case Building = 'building';

    /** The last build failed. It is tried again once the data or settings move on. */
    case Failed = 'failed';
}
