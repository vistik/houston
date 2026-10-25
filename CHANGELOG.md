# Changelog

All notable changes to Houston are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Every metric on the repository and team reports has its own icon next to its name.
- Pick the timezone reports are shown in under Settings → Defaults. Reports use UTC until one is picked.
- `HOUSTON_DEMO=true` runs Houston as a read-only public demo. Visitors can look around and switch layouts and views for themselves, but can't watch, remove or sync repositories, and settings are hidden. The scheduler keeps syncing the watched repositories, so the numbers stay up to date, but no visitor's request talks to GitHub.

### Changed

- "Last N days" means today and the N − 1 days before it, from midnight in that timezone, instead of exactly N × 24 hours back from now. Each bar on a chart is a whole calendar day, also across daylight saving changes, and the bars add up to the headline numbers.
- The previous period a report compares with is as long as the time that has passed in the current one, so a day still under way doesn't read as a drop.
- Custom ranges and working hours use the same timezone, and merge and deploy dates are shown in it.
- The timezone dropdown can be searched.
- `composer dev` starts one queue worker instead of five.

### Removed

- `HOUSTON_TIMEZONE`. If you had set it, pick the same timezone under Settings → Defaults.

### Fixed

- Chart dates no longer show a day early for viewers west of UTC.
- Dropdowns wider than their button line up with its right edge instead of running past the page.

## [0.1.0]

The first release.

### Added

- Syncs pull requests, reviews, CI workflow runs, deployments and releases from GitHub into a local cache, pausing before GitHub's rate limits.
- Pull request flow: throughput, cycle time, lead time, pickup and review time, time to approval, merge delay, size, and time spent waiting on reviewers versus authors.
- Review quality: unreviewed merges, changes requested, review rounds, rework, and how concentrated reviewing is.
- CI: failure rates on the default branch and on pull requests, flaky and rerun rates, duration, queue time, and how long the default branch stays red.
- Delivery (DORA): deploy frequency, lead time to production, change failure rate and time to restore, from GitHub Deployments or releases.
- Teams: pull request metrics across every watched repository for the people on a team, counted for the team someone was on at the time.
- Benchmarks against DORA and LinearB, or against your own targets.
- Wall: a rotating full-screen view for an office TV.
- MCP server with read-only tools for agents, and a `give-feedback` prompt.
- Working time: durations that measure people only count working hours.
- Optional login for servers other people can reach, set up with `php artisan houston:install`.

[Unreleased]: https://github.com/vistik/Houston/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/vistik/Houston/releases/tag/v0.1.0
