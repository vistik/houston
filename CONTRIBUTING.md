# Contributing to Houston

Thanks for helping out! Bug reports, ideas and pull requests are all welcome.

Everyone taking part is expected to follow the [code of conduct](CODE_OF_CONDUCT.md).

## Reporting bugs and suggesting features

Open an [issue](https://github.com/vistik/Houston/issues) and pick the form that fits. For bugs, include what you did, what you expected and what happened instead. A metric that looks wrong is easiest to fix when you also say which repository and period it was.

Please report security issues privately instead; see [SECURITY.md](SECURITY.md).

## Setting up

Follow the [installation steps](README.md#installation), then run `composer run dev`.

The tests use an in-memory SQLite database and fake every GitHub request, so they need no token or network.

## Making changes

- Branch off `main`, and keep each pull request to one change.
- Add or update tests for any change in behaviour. Most tests are Pest feature tests in `tests/Feature`, and they build their data with the model factories.
- Follow the style of the code around your change. Pint, PHPStan and the frontend checks enforce most of it.
- New metrics implement `App\Metrics\Metric`, are listed in `App\Metrics\Metrics::Definitions`, and describe in plain words how they are calculated.

Before you open a pull request, run everything CI runs:

```bash
composer ci:check
```

CI also fails when PHP line coverage drops below 95%. To check it locally, install [PCOV](https://github.com/krakjoe/pcov) or Xdebug and run `composer test:coverage`.

Add a line under `Unreleased` in [CHANGELOG.md](CHANGELOG.md) for changes people will notice.

To fix formatting:

```bash
composer lint        # PHP, with Pint
npm run check:fix    # TypeScript and CSS
```

## Project layout

| Path           | What lives there                                                                              |
| -------------- | --------------------------------------------------------------------------------------------- |
| `app/GitHub`   | The GitHub client, rate limit bookkeeping, and the synchronizers that fill the local cache.   |
| `app/Metrics`  | Metric definitions and the building blocks they share: periods, windows, working time, stats. |
| `app/Reports`  | The velocity report that brings every metric together, and its cache.                         |
| `app/Settings` | App-wide settings people change in the UI.                                                    |
| `app/Enums`    | Enums for model attributes and app-wide choices. Enums used by one area live in that area.    |
| `app/Mcp`      | The MCP server, its tools and its prompt.                                                     |
| `resources/js` | The React frontend, rendered through Inertia.                                                 |

## License

By contributing, you agree that your contributions are licensed under the [MIT license](LICENSE).
