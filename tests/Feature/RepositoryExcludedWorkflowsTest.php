<?php

use App\Metrics\BranchStatus;
use App\Metrics\Period;
use App\Models\Repository;
use App\Models\WorkflowRun;
use App\Reports\ReportCache;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->synced()->create();
    $this->now = CarbonImmutable::now();

    $factory = WorkflowRun::factory()->for($this->repository);

    // "tests" passes; "Changelog Label" stays red until someone adds a label.
    $factory->at($this->now->subDays(2), 10)->create(['workflow_id' => 1, 'name' => 'tests', 'head_sha' => 'abc']);
    $factory->failed()->at($this->now->subDays(2), 1)->create(['workflow_id' => 2, 'name' => 'Changelog Label', 'head_sha' => 'abc']);
    $factory->failed()->at($this->now->subDay(), 1)->create(['workflow_id' => 2, 'name' => 'Changelog Label', 'head_sha' => 'def']);
});

function fullReport(Repository $repository): array
{
    return app(ReportCache::class)->full($repository->fresh('sync'), Period::lastDays(7, 'UTC'));
}

it('leaves excluded workflows out of the CI metrics but still lists them', function () {
    $before = fullReport($this->repository);

    expect($before['kpis']['ciFailureRate']['value'])->toEqual(2 / 3)
        ->and($before['defaultBranchStatus']['state'])->toBe(BranchStatus::Failing);

    $this->travel(1)->seconds();
    $this->put(route('repositories.excluded-workflows', $this->repository), ['workflow_ids' => [2]])->assertRedirect();

    $after = fullReport($this->repository);
    $workflows = collect($after['workflows'])->keyBy('name');

    expect($this->repository->fresh()->excluded_workflow_ids)->toBe([2])
        ->and($after['kpis']['ciFailureRate']['value'])->toEqual(0)
        ->and($after['kpis']['ciRuns']['value'])->toBe(1)
        ->and($after['kpis']['ciDuration']['value'])->toEqual(10 * 60)
        ->and($after['defaultBranchStatus']['state'])->toBe(BranchStatus::Passing)
        ->and($workflows['Changelog Label']['excluded'])->toBeTrue()
        ->and($workflows['tests']['excluded'])->toBeFalse();
});

it('includes every workflow again when the exclusions are cleared', function () {
    $this->repository->update(['excluded_workflow_ids' => [2]]);

    $this->put(route('repositories.excluded-workflows', $this->repository), ['workflow_ids' => []])->assertRedirect();

    expect($this->repository->fresh()->excluded_workflow_ids)->toBeNull()
        ->and(fullReport($this->repository)['kpis']['ciFailureRate']['value'])->toEqual(2 / 3);
});

it('only accepts a list of workflow ids', function () {
    $this->put(route('repositories.excluded-workflows', $this->repository), [])->assertSessionHasErrors('workflow_ids');
    $this->put(route('repositories.excluded-workflows', $this->repository), ['workflow_ids' => ['label']])->assertSessionHasErrors('workflow_ids.0');

    expect($this->repository->fresh()->excluded_workflow_ids)->toBeNull();
});
