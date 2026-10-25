<?php

namespace App\Mcp\Tools\Concerns;

use App\Enums\RepositorySyncStatus;
use App\Metrics\Period;
use App\Models\Repository;
use App\Settings\ReportDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Shared input handling for tools that read a watched repository's cached data.
 */
trait ReadsRepositories
{
    protected function repositorySchema(JsonSchema $schema): Type
    {
        return $schema->string()
            ->description('The repository as "owner/name", e.g. "laravel/framework". Use list-repositories to see what is watched.')
            ->required();
    }

    /**
     * The reporting window: the last `days` days, or a custom range when `from` and `to` are given.
     *
     * @return array<string, Type>
     */
    protected function periodSchema(JsonSchema $schema, int $default = 30): array
    {
        return [
            'days' => $schema->integer()
                ->enum(Period::AllowedDays)
                ->description('Length of the reporting window in days, ending now. Metrics are compared with the equally long window right before it. Ignored when from and to are given.')
                ->default($default),
            'from' => $schema->string()
                ->format('date')
                ->description('First day of a custom range as YYYY-MM-DD, such as the start of a sprint or the week before an incident. Give it together with to; it replaces days. Metrics are compared with the equally long range right before it.'),
            'to' => $schema->string()
                ->format('date')
                ->description('Last day of a custom range as YYYY-MM-DD, inclusive. Give it together with from. Ranges are kept within the cached history and never run past today.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function periodRules(): array
    {
        return [
            'days' => ['sometimes', 'integer', Rule::in(Period::AllowedDays)],
            'from' => ['required_with:to', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @param  Repository|null  $repository  Keeps custom ranges within this repository's history instead of the longest one watched.
     */
    protected function period(Request $request, int $default = 30, ?Repository $repository = null): Period
    {
        if (filled($request->get('from')) && filled($request->get('to'))) {
            return Period::between(
                CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->get('from')),
                CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->get('to')),
                ReportDefaults::timezone(),
                $repository?->lookbackDays() ?? Repository::longestLookbackDays(),
            );
        }

        return Period::lastDays((int) $request->get('days', $default), ReportDefaults::timezone());
    }

    /**
     * @throws ValidationException
     */
    protected function resolveRepository(Request $request): Repository
    {
        $request->validate(['repository' => ['required', 'string']]);

        [$owner, $name] = array_pad(explode('/', trim((string) $request->get('repository')), 2), 2, '');

        $repository = Repository::query()->with('sync')->where('owner', $owner)->where('name', $name)->first();

        if ($repository === null) {
            $watched = Repository::query()->orderBy('owner')->orderBy('name')->get()->map->fullName()->implode(', ');

            throw ValidationException::withMessages([
                'repository' => "Houston does not watch [{$request->get('repository')}]. Watched repositories: ".($watched ?: 'none').'.',
            ]);
        }

        return $repository;
    }

    /**
     * Describe the repository and how fresh its cached data is, so the agent can caveat its conclusions.
     *
     * @return array<string, mixed>
     */
    protected function repositoryPayload(Repository $repository): array
    {
        $sync = $repository->sync;

        return [
            'fullName' => $repository->fullName(),
            'url' => "https://github.com/{$repository->fullName()}",
            'description' => $sync?->description,
            'defaultBranch' => $sync?->default_branch,
            'sync' => [
                'status' => $sync->status ?? RepositorySyncStatus::Pending,
                'hasData' => $sync?->pull_requests_synced_at !== null,
                'finishedAt' => $sync?->finished_at?->toIso8601String(),
                'isStale' => $repository->isStale(),
                'error' => $sync?->error,
            ],
        ];
    }
}
