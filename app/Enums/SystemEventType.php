<?php

namespace App\Enums;

/**
 * What a system event records, as "category.action". The log groups events by the category.
 */
enum SystemEventType: string
{
    case CacheFlushed = 'cache.flushed';

    case CacheWarmed = 'cache.warmed';

    case RepositoryAdded = 'repository.added';

    case RepositoryRemoved = 'repository.removed';

    case SettingsBenchmarks = 'settings.benchmarks';

    case SettingsDefaults = 'settings.defaults';

    case SettingsExcludedWorkflows = 'settings.excluded_workflows';

    case SettingsLookback = 'settings.lookback';

    case SyncQueued = 'sync.queued';

    case SyncStarted = 'sync.started';

    case SyncFinished = 'sync.finished';

    case SyncSkipped = 'sync.skipped';

    case SyncRateLimited = 'sync.rate_limited';

    case SyncFailed = 'sync.failed';

    case TeamCreated = 'team.created';

    case TeamRenamed = 'team.renamed';

    case TeamDeleted = 'team.deleted';

    case TeamMemberJoined = 'team.member_joined';

    case TeamMemberLeft = 'team.member_left';

    case TeamMemberUpdated = 'team.member_updated';

    case TeamMemberRemoved = 'team.member_removed';

    case TimelineEventAdded = 'timeline.event_added';

    case TimelineEventUpdated = 'timeline.event_updated';

    case TimelineEventRemoved = 'timeline.event_removed';
}
