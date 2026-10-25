<?php

namespace App\Enums;

/**
 * A step of a repository sync. The cases are in the order the phases run.
 */
enum RepositorySyncPhase: string
{
    case Details = 'details';

    case PullRequests = 'pull_requests';

    case OpenPullRequests = 'open_pull_requests';

    case WorkflowRuns = 'workflow_runs';

    case Deployments = 'deployments';

    public function label(): string
    {
        return match ($this) {
            self::Details => 'Repository details',
            self::PullRequests => 'Pull requests',
            self::OpenPullRequests => 'Long-lived open pull requests',
            self::WorkflowRuns => 'Workflow runs',
            self::Deployments => 'Deployments',
        };
    }
}
