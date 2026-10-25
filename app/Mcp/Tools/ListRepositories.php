<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadsRepositories;
use App\Models\Repository;
use App\Reports\ReportCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List every repository Houston watches, with headline velocity KPIs (throughput, cycle time, pickup time, CI failure rate on the default branch unless the report defaults widen it, open and stale pull requests, deployments, change failure rate), a daily merge trend and default branch CI status.')]
class ListRepositories extends Tool
{
    use ReadsRepositories;

    public function __construct(private ReportCache $reports) {}

    public function handle(Request $request): Response
    {
        $request->validate($this->periodRules());
        $period = $this->period($request);

        return Response::json([
            ...$period->filters(),
            'repositories' => Repository::query()->with('sync')->orderBy('owner')->orderBy('name')->get()
                ->map(fn (Repository $repository): array => [
                    ...$this->repositoryPayload($repository),
                    ...$this->reports->summary($repository, $period),
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
