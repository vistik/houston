# Houston

Houston measures engineering velocity for GitHub repositories. It syncs pull requests, reviews, CI workflow runs and deployments into a local cache, then reports how work flows from the first commit to production.

- **Pull request flow:** throughput, cycle time, lead time, pickup and review time, time to approval, merge delay, size, and how long a pull request waits on reviewers versus its author.
- **Review quality:** unreviewed merges, changes requested, review rounds, rework, and how concentrated reviewing is.
- **CI:** failure rates on the default branch and on pull requests, flaky and rerun rates, duration, queue time, and how long the default branch stays red.
- **Delivery (DORA):** deploy frequency, lead time to production, change failure rate and time to restore. These come from GitHub Deployments, or from releases for repositories that don't deploy.
- **Dependencies:** how many direct Composer and npm dependencies on the default branch are behind their latest stable release, and how many by a major version, compared with a month earlier.
- **Teams:** the same pull request metrics across every watched repository, for the people on a team. Work counts for the team someone was on when they did it.
- **Benchmarks:** rate each metric against DORA and LinearB benchmarks, or against your own targets.
- **Wall:** a rotating full-screen view for an office TV.
- **MCP server:** agents can read the reports, for example to write feedback on a sprint.

Durations that measure people only count working time, so a pull request opened on Friday evening doesn't look two days slower than one opened on Monday.

## Requirements

- PHP 8.5
- Composer
- Node 22 and npm
- A GitHub token that can read the repositories you want to measure

Houston uses SQLite by default. Any database Laravel supports works.

## Installation

```bash
git clone https://github.com/vistik/Houston.git
cd Houston
composer setup
```

`composer setup` installs the dependencies, creates `.env`, generates the app key, migrates the database, asks whether Houston should require a login, and builds the frontend.

### Login

Houston asks for a login by default, and shows the install guide until one exists. Create it with:

```bash
php artisan houston:install
```

It asks whether Houston should require a login, creates the one login it has (an email and a password), and sets `HOUSTON_AUTH` in `.env`. It also sets `HOUSTON_LANDING=false`, so `/` goes straight to the dashboard. Run it again to change the password, or turn the login off on your own machine. The MCP endpoint doesn't use the login; it has its own token.

For scripts and servers without a terminal, `php artisan houston:install --generate` turns the login on without asking anything. It uses the email from `--email`, or else keeps the existing login's email (or uses `houston@example.com`), sets a random password, and prints both.

### GitHub token

Set `GITHUB_TOKEN` in `.env`. When it is empty, Houston uses the token of the GitHub CLI (`gh auth token`), which is handy on your own machine.

A [fine-grained token](https://github.com/settings/personal-access-tokens/new) needs read access to these repository permissions:

| Permission    | Used for                                                             |
| ------------- | -------------------------------------------------------------------- |
| Metadata      | Repository details                                                   |
| Pull requests | Pull requests and reviews                                            |
| Actions       | Workflow runs                                                        |
| Deployments   | Deployments                                                          |
| Contents      | Releases, commit timestamps, and dependency manifests and lock files |

A classic token needs no scopes for public repositories, and the `repo` scope for private ones.

After each sync, Houston reads `composer.json`, `composer.lock`, `package.json` and `package-lock.json` at the root of the default branch, and asks [Packagist](https://packagist.org) and the [npm registry](https://www.npmjs.com) for the latest release of each direct dependency. Those answers are cached for six hours and shared by every repository. Private packages, packages that track a branch, and npm packages without a `package-lock.json` (Yarn, pnpm) can't be compared and are counted separately.

### Running it

Start everything Houston needs in development with:

```bash
composer run dev
```

This starts the web server at `http://localhost:8000`, the scheduler, five queue workers so repositories sync side by side, a log tail and Vite.

#### Running in production

On a server, check these before you let others in:

- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
- Keep the login on (`HOUSTON_AUTH=true`, the default) and create it with `php artisan houston:install`.
- Run a queue worker (`php artisan queue:work`) under a process monitor such as Supervisor.
- Run the [scheduler](https://laravel.com/docs/scheduling#running-the-scheduler). It refreshes every watched repository every 15 minutes.
- Set `HOUSTON_MCP_TOKEN` only if you want the MCP endpoint. Without it, `/mcp` returns 404.

#### Running a public demo

To let anyone look around without a login, set Houston up as usual, then turn on `HOUSTON_DEMO=true`. Watch the repositories, add the teams and timeline events and pick the defaults first, because the demo can't change them.

The demo is read-only. Visitors can switch layouts, views and cards, which only they see, but can't watch, remove or sync repositories, and **Settings** is hidden. The login and `APP_DEBUG` are turned off. Run the scheduler and a queue worker as usual: they keep syncing the watched repositories so the numbers stay up to date, but no visitor's request talks to GitHub. Give `GITHUB_TOKEN` a fine-grained token that can only read public repositories and watch only public ones, and leave `HOUSTON_MCP_TOKEN` empty.

Before you upgrade, read [CHANGELOG.md](CHANGELOG.md) for what changed.

## Adding your data

1. Open Houston and click **Watch repository**.
2. Enter `owner/name`, or paste the repository's GitHub URL.

Houston queues a sync straight away. The first sync pulls the lookback window (180 days by default) and can take a while on busy repositories. It pauses before it runs into GitHub's rate limits, and resumes when they reset. You can follow its progress under **Settings → Syncs**.

You can also sync from the command line:

```bash
php artisan houston:sync                 # every watched repository
php artisan houston:sync laravel/framework
php artisan houston:sync --queue         # dispatch to the queue instead of running inline
php artisan houston:flush                # wipe the cache, keeping the watched repositories
php artisan houston:install              # turn the login on or off, or change it
php artisan houston:install --generate   # turn the login on with a random password, without asking anything
php artisan houston:install --generate --email=you@example.com
```

To report on teams, create them under **Settings → Teams** and add people by their GitHub login.

## Configuration

Everything Houston-specific lives in `config/houston.php`. These can be set from `.env`:

| Variable                         | Default           | Description                                                                                                             |
| -------------------------------- | ----------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `GITHUB_TOKEN`                   | `gh auth token`   | The token used to talk to GitHub.                                                                                       |
| `HOUSTON_LOOKBACK_DAYS`          | `180`             | Days of history a fresh sync pulls. Keep it at least twice the longest period you report on. Can be set per repository. |
| `HOUSTON_STALE_AFTER_MINUTES`    | `15`              | Viewing a repository whose cache is older than this queues a refresh.                                                   |
| `HOUSTON_STALE_PR_DAYS`          | `7`               | Open pull requests without activity for this long are flagged as stale.                                                 |
| `HOUSTON_WORKING_DAYS`           | `1,2,3,4,5`       | Working days, as ISO day numbers (1 is Monday).                                                                         |
| `HOUSTON_WORKDAY_START` / `_END` | `00:00` / `24:00` | Working hours. Use all seven days and `00:00`–`24:00` to measure calendar time.                                         |
| `HOUSTON_MCP_TOKEN`              | _empty_           | Enables the MCP endpoint at `/mcp` for clients that send this bearer token.                                             |
| `HOUSTON_AUTH`                   | `true`            | Requires the login created by `php artisan houston:install` before showing anything.                                    |
| `HOUSTON_DEMO`                   | `false`           | Runs a read-only public demo. See [Running a public demo](#running-a-public-demo).                                      |

To send errors to [Sentry](https://sentry.io), set `SENTRY_LARAVEL_DSN`. Leave it empty to turn Sentry off.

The rate limit reserve, which deployment environments count as production, and which pull requests count as remediations (reverts, hotfixes and rollbacks) are set in `config/houston.php` itself.

Report defaults (period, grouping and timezone) and benchmarks are set in the app under **Settings**. Every report is shown in that one timezone: daily charts, custom ranges and working hours all start and end the day at midnight there, and "last 30 days" means today and the 29 days before it. Each repository's lookback window is set under **Settings → Syncs**, and the CI workflows to leave out of its metrics on the repository's page.

## MCP server

Agents can read Houston over the [Model Context Protocol](https://modelcontextprotocol.io). It offers three read-only tools: `list-repositories`, `get-velocity-report` and `list-pull-requests`. It also offers a `give-feedback` prompt that walks through a full review.

Locally, point your client at:

```bash
php artisan mcp:start houston
```

Over HTTP, set `HOUSTON_MCP_TOKEN` and send it as a bearer token to `/mcp`. The endpoint returns 404 while no token is set.

## Development

```bash
composer ci:check       # everything CI runs: frontend lint, types and tests, Pint, PHPStan and the tests
composer test           # Pint, PHPStan and the tests
composer test:coverage  # the tests with the 95% line coverage gate CI uses (needs PCOV or Xdebug)
php artisan test        # the tests only
npm run check:fix       # format and lint the frontend
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to contribute, and the [code of conduct](CODE_OF_CONDUCT.md).

## Security

Please report vulnerabilities privately; see [SECURITY.md](SECURITY.md).

## License

Houston is open-source software licensed under the [MIT license](LICENSE).
