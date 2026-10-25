<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\GiveFeedback;
use App\Mcp\Tools\GetVelocityReport;
use App\Mcp\Tools\ListPullRequests;
use App\Mcp\Tools\ListRepositories;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Houston')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
    Houston measures engineering velocity for GitHub repositories from a local cache of pull requests, reviews, CI workflow runs and deployments. All tools are read-only.

    Start with list-repositories, then get-velocity-report for a repository, and drill into examples with list-pull-requests. The give-feedback prompt walks through a full review.

    Reading the data:
    - Every tool takes a reporting window: days (7, 14, 30, 45, 90, 180 or 365, ending now), or from and to (YYYY-MM-DD, inclusive) for a custom range such as a sprint or the weeks around an incident.
    - KPIs come as {value, previous, unit, better}. "previous" is the same metric over the equally long window or range right before the current one. "better" says whether higher or lower is an improvement ("neutral" when neither). KPIs that sum up one value per item, like cycle time per merged pull request, also carry "statistic" (whether value is the "median" or the "average") and both "median" and "average" for the current window, so a skewed spread shows.
    - Units: "duration" values are seconds, "ratio" values are 0..1, "lines" are added plus deleted lines. null means there was no data to compute it from.
    - Durations that measure people (pickup, review, approval, merge, recovery) count working time only; the report's workingTime field describes the schedule. CI durations are wall-clock. Raw pull request timestamps are wall-clock.
    - Cycle time runs from opening to merge; lead time from the first commit to merge; pickup time from ready for review to the first human response.
    - ciFailureRate counts workflow runs on the default branch outside pull requests, unless the report defaults widen it to every branch; the full report's ciFailureRateScope says which ("default_branch" or "all_branches"). ciDefaultBranchFailureRate and ciPullRequestFailureRate always cover just those runs. CI duration, queue time, re-run and flaky rates, runs and minutes cover every branch.
    - Delivery follows DORA: deploy frequency, lead time to production, change failure rate and time to restore. A deployment ships every pull request merged since the previous one, and counts as a failed change when the next deployment ships a remediation (a revert, hotfix or rollback).
    - Bot-authored pull requests are excluded unless include_bots is set; the report's botBacklog covers them separately.
    - Each response includes a sync block. When the data is stale or a sync failed, mention it before drawing conclusions.
    MD)]
class HoustonServer extends Server
{
    protected array $tools = [
        ListRepositories::class,
        GetVelocityReport::class,
        ListPullRequests::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        GiveFeedback::class,
    ];
}
