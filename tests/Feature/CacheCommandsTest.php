<?php

use App\Enums\RepositorySyncStatus;
use App\GitHub\RepositorySynchronizer;
use App\Jobs\SyncRepository;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RepositorySync;
use App\Models\WorkflowRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('flushes every cached table but keeps the watched repositories', function () {
    $repository = Repository::factory()->synced()->create();
    PullRequest::factory()->for($repository)->create();
    WorkflowRun::factory()->for($repository)->create();

    $this->artisan('houston:flush', ['--force' => true])->assertSuccessful();

    expect(Repository::count())->toBe(1)
        ->and(PullRequest::count())->toBe(0)
        ->and(WorkflowRun::count())->toBe(0)
        ->and(RepositorySync::count())->toBe(0)
        ->and($repository->fresh()->isStale())->toBeTrue();
});

it('records sync failures without crashing the command', function () {
    Http::fake(['api.github.com/graphql' => Http::response(['errors' => [['message' => 'Could not resolve to a Repository']]])]);

    $repository = Repository::factory()->create(['owner' => 'acme', 'name' => 'gone']);

    $this->artisan('houston:sync', ['repository' => 'acme/gone'])->assertFailed();

    expect($repository->sync->status)->toBe(RepositorySyncStatus::Failed)
        ->and($repository->sync->error)->toContain('Could not resolve');
});

it('skips a repository that is already being synced elsewhere', function () {
    $repository = Repository::factory()->synced()->create(['owner' => 'acme', 'name' => 'busy']);
    $lock = Cache::lock("houston:sync:{$repository->id}", 60);
    $lock->get();

    $this->artisan('houston:sync', ['repository' => 'acme/busy'])
        ->expectsOutputToContain('SKIPPED')
        ->assertSuccessful();

    expect($repository->sync->fresh()->status)->toBe(RepositorySyncStatus::Succeeded);

    $lock->release();
});

it('lets a queued sync step aside while another sync holds the repository', function () {
    $repository = Repository::factory()->create();
    Cache::lock("houston:sync:{$repository->id}", 60)->get();

    (new SyncRepository($repository))->handle(app(RepositorySynchronizer::class));

    Http::assertNothingSent();
});

it('refuses to flush while a sync is running', function () {
    $repository = Repository::factory()->synced()->create();
    PullRequest::factory()->for($repository)->create();
    Cache::lock("houston:sync:{$repository->id}", 60)->get();

    $this->artisan('houston:flush', ['--force' => true])->assertFailed();

    expect(PullRequest::count())->toBe(1);
});

it('rejects a repository argument that is not owner/name', function (string $argument) {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);

    $this->artisan('houston:sync', ['repository' => $argument])
        ->expectsOutputToContain("Invalid repository [{$argument}]")
        ->assertFailed();

    Http::assertNothingSent();
})->with(['foo', 'acme/', '/app', 'acme/app/extra', 'acme/ap p']);

it('says when a well-formed repository is not watched and lists the watched ones', function () {
    Repository::factory()->create(['owner' => 'acme', 'name' => 'app']);
    Repository::factory()->create(['owner' => 'acme', 'name' => 'api']);

    // The watched repositories come out as one list, so assert on the output as a whole.
    expect(Artisan::call('houston:sync', ['repository' => 'acme/missing']))->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('acme/missing is not a watched repository.', 'acme/api', 'acme/app');

    Http::assertNothingSent();
});

it('says when a repository is not watched and nothing is watched at all', function () {
    $this->artisan('houston:sync', ['repository' => 'acme/missing'])
        ->expectsOutputToContain('acme/missing is not a watched repository.')
        ->doesntExpectOutputToContain('Watched repositories')
        ->assertFailed();
});

it('warns when there are no watched repositories to sync', function () {
    $this->artisan('houston:sync')
        ->expectsOutputToContain('No matching watched repositories.')
        ->assertFailed();
});
