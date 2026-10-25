<?php

use App\Mcp\Prompts\GiveFeedback;
use App\Mcp\Servers\HoustonServer;
use App\Metrics\Period;
use Illuminate\Testing\TestResponse;

function mcpRequest(string $method): TestResponse
{
    config(['houston.mcp_token' => 'secret']);

    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) []], [
        'Authorization' => 'Bearer secret',
        'Accept' => 'application/json, text/event-stream',
    ]);
}

it('registers every tool and the feedback prompt', function () {
    expect(collect(mcpRequest('tools/list')->json('result.tools'))->pluck('name')->all())
        ->toBe(['list-repositories', 'get-velocity-report', 'list-pull-requests']);

    HoustonServer::prompts()->assertRegistered(GiveFeedback::class);
});

it('describes the input of the velocity report tool', function () {
    $tools = collect(mcpRequest('tools/list')->assertOk()->json('result.tools'))->keyBy('name');
    $schema = $tools['get-velocity-report']['inputSchema'];

    expect($schema['required'])->toBe(['repository'])
        ->and($schema['properties']['repository']['type'])->toBe('string')
        ->and($schema['properties']['days']['enum'])->toBe(Period::AllowedDays)
        ->and($schema['properties']['days']['default'])->toBe(30)
        ->and($schema['properties']['from']['format'])->toBe('date')
        ->and($schema['properties']['to']['format'])->toBe('date')
        ->and($schema['properties']['include_bots'])->toMatchArray(['type' => 'boolean', 'default' => false])
        ->and($tools['get-velocity-report']['annotations']['readOnlyHint'])->toBeTrue();
});

it('describes the filters and sort orders of the pull request tool', function () {
    $schema = collect(mcpRequest('tools/list')->json('result.tools'))->keyBy('name')['list-pull-requests']['inputSchema'];

    expect($schema['required'])->toBe(['repository'])
        ->and($schema['properties']['state'])->toMatchArray(['enum' => ['open', 'merged', 'closed', 'all'], 'default' => 'merged'])
        ->and($schema['properties']['sort'])->toMatchArray(['enum' => ['recent', 'oldest', 'largest', 'slowest'], 'default' => 'recent'])
        ->and($schema['properties']['limit'])->toMatchArray(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25])
        ->and($schema['properties']['author']['type'])->toBe('string')
        ->and($schema['properties'])->toHaveKeys(['days', 'from', 'to', 'include_bots']);
});

it('lists the arguments of the feedback prompt, all of them optional', function () {
    $prompt = collect(mcpRequest('prompts/list')->assertOk()->json('result.prompts'))->firstWhere('name', 'give-feedback');

    expect(collect($prompt['arguments'])->pluck('name')->all())->toBe(['repository', 'days', 'from', 'to', 'focus'])
        ->and(collect($prompt['arguments'])->pluck('required')->unique()->all())->toBe([false]);
});
