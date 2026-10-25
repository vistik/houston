<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadsRepositories;
use App\Reports\ReportCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the full velocity report for one repository: every KPI with its previous-period value, cycle time breakdown by stage, cycle time and size distributions, trend buckets, work in progress, CI workflows, slowest merged pull requests, default branch status, DORA delivery metrics, remediations and the bot backlog.')]
class GetVelocityReport extends Tool
{
    use ReadsRepositories;

    public function __construct(private ReportCache $reports) {}

    public function handle(Request $request): Response
    {
        $request->validate([...$this->periodRules(), 'include_bots' => ['sometimes', 'boolean']]);
        $repository = $this->resolveRepository($request);

        return Response::json([
            'repository' => $this->repositoryPayload($repository),
            'report' => $this->reports->full($repository, $this->period($request, repository: $repository), (bool) $request->get('include_bots', false)),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repository' => $this->repositorySchema($schema),
            ...$this->periodSchema($schema),
            'include_bots' => $schema->boolean()
                ->description('Count pull requests authored by bots (Dependabot, Renovate, ...) in the metrics. Off by default so they do not skew people metrics.')
                ->default(false),
        ];
    }
}
