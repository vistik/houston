<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub Token
    |--------------------------------------------------------------------------
    |
    | The token used to talk to the GitHub API. When empty, Houston falls back
    | to the token of the locally authenticated GitHub CLI (`gh auth token`).
    |
    */

    'github_token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | GitHub Rate Limit Reserve
    |--------------------------------------------------------------------------
    |
    | Syncs pause once fewer than this many requests (or GraphQL points) are
    | left in the current rate limit window, leaving room for anything else
    | sharing the token. Paused syncs resume when the window resets.
    |
    */

    'github_rate_limit_reserve' => 50,

    /*
    |--------------------------------------------------------------------------
    | Lookback Window
    |--------------------------------------------------------------------------
    |
    | How many days of history to pull into the cache on a fresh sync. Keep
    | this at least twice the longest reporting period so period-over-period
    | comparisons have data to compare against.
    |
    */

    'lookback_days' => (int) env('HOUSTON_LOOKBACK_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Cache Freshness
    |--------------------------------------------------------------------------
    |
    | Viewing a repository whose cache is older than this many minutes will
    | queue a background refresh.
    |
    */

    'stale_after_minutes' => (int) env('HOUSTON_STALE_AFTER_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Stale Pull Request Threshold
    |--------------------------------------------------------------------------
    |
    | Open pull requests without any activity for this many days are flagged.
    |
    */

    'stale_pull_request_days' => (int) env('HOUSTON_STALE_PR_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Working Time
    |--------------------------------------------------------------------------
    |
    | Durations that measure people (pickup, review, time to merge, recovery)
    | only count working time, so a PR opened on Friday evening doesn't look
    | two days slower than one opened on Monday. Days use ISO numbering
    | (1 = Monday). Use all seven days and 00:00-24:00 for calendar time.
    | CI run durations are always wall-clock time. Working time is counted in
    | the timezone reports are shown in, picked under Settings → Defaults.
    |
    */

    'working_time' => [
        'days' => array_map('intval', explode(',', (string) env('HOUSTON_WORKING_DAYS', '1,2,3,4,5'))),
        'start' => env('HOUSTON_WORKDAY_START', '00:00'),
        'end' => env('HOUSTON_WORKDAY_END', '24:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployments
    |--------------------------------------------------------------------------
    |
    | Houston measures delivery from GitHub Deployments to an environment
    | matching this pattern. Repositories without such deployments fall back
    | to published (non pre-release) GitHub releases.
    |
    */

    'deploy_environment_pattern' => '/^(prod|production|live)\b/i',

    /*
    |--------------------------------------------------------------------------
    | Remediation Pull Requests
    |--------------------------------------------------------------------------
    |
    | Pull requests that fix a bad change: reverts and hotfixes. They drive
    | the change failure rate. Matched against the title, branch and labels.
    |
    */

    'remediation_pattern' => '/^(revert|hotfix|rollback)\b/i',

    /*
    |--------------------------------------------------------------------------
    | MCP Token
    |--------------------------------------------------------------------------
    |
    | Agents can read Houston over MCP. Locally, run `php artisan mcp:start
    | houston`. The HTTP endpoint at /mcp is only enabled when this token is
    | set, and clients must send it as a bearer token.
    |
    */

    'mcp_token' => env('HOUSTON_MCP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    |
    | Whether Houston asks for a login before showing anything. It is on
    | unless you turn it off, say on your own machine. Houston has a single
    | login, created by `php artisan houston:install`; until then visitors
    | see the install guide. The MCP endpoint keeps using its token.
    |
    */

    'auth' => (bool) env('HOUSTON_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Landing Page
    |--------------------------------------------------------------------------
    |
    | Whether / and /install show the Houston website. Meant for a public
    | instance that presents Houston itself. The dashboard lives at
    | /dashboard either way; without the website, / sends you there.
    |
    */

    'landing' => (bool) env('HOUSTON_LANDING', false),

    /*
    |--------------------------------------------------------------------------
    | Public Demo
    |--------------------------------------------------------------------------
    |
    | Turns Houston into a read-only demo anyone may look around. There is
    | no login, nothing visitors do is saved for others, settings are hidden
    | and their requests never talk to GitHub. The scheduler keeps syncing
    | the watched repositories, so the numbers stay up to date.
    |
    */

    'demo' => (bool) env('HOUSTON_DEMO', false),

];
