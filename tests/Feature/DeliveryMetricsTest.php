<?php

use App\Enums\DeploymentSource;
use App\Enums\PullRequestState;
use App\Metrics\Delivery\DeliveryMetric;
use App\Metrics\Delivery\DeliveryWindow;
use App\Metrics\DeliveryMetrics;
use App\Metrics\Metrics;
use App\Metrics\Window;
use App\Metrics\WorkingTime;
use App\Models\Deployment;
use App\Models\PullRequest;
use App\Models\Repository;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-07 12:00:00');
    $this->repository = Repository::factory()->create();
    $this->now = CarbonImmutable::now();
    $this->calendar = new WorkingTime('UTC', [1, 2, 3, 4, 5, 6, 7], '00:00', '24:00');
    $this->inWindow = fn (?CarbonImmutable $moment): bool => $moment !== null && $moment->gte($this->now->subDays(7));
});

function delivery(Repository $repository, WorkingTime $time): DeliveryMetrics
{
    return new DeliveryMetrics($repository->deployments()->get(), $repository->pullRequests()->get(), 'main', $time);
}

/**
 * Every delivery metric over a window, keyed like a report's KPIs.
 *
 * @param  callable(?CarbonImmutable): bool  $inWindow
 * @return array<string, array{value: float|int|null}>
 */
function deliveryMetrics(DeliveryMetrics $delivery, callable $inWindow, int $days): array
{
    $window = new Window(makeDelivery: fn (): DeliveryWindow => $delivery->window($inWindow, $days));

    return collect(Metrics::all())
        ->whereInstanceOf(DeliveryMetric::class)
        ->map(fn (DeliveryMetric $metric): array => ['value' => $metric->calculate($window)])
        ->all();
}

function mergedPullRequest(Repository $repository, CarbonImmutable $mergedAt, array $attributes = []): PullRequest
{
    return PullRequest::factory()->for($repository)->create([
        'state' => PullRequestState::Merged,
        'opened_at' => $mergedAt->subHours(4),
        'first_commit_at' => $mergedAt->subHours(6),
        'merged_at' => $mergedAt,
        'base_ref' => 'main',
        ...$attributes,
    ]);
}

it('measures deploy frequency and lead time from first commit to production', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create();
    mergedPullRequest($this->repository, $this->now->subDays(5));
    mergedPullRequest($this->repository, $this->now->subDays(5)->addHours(2));
    Deployment::factory()->for($this->repository)->at($this->now->subDays(4))->create();
    mergedPullRequest($this->repository, $this->now->subDays(3));
    Deployment::factory()->for($this->repository)->at($this->now->subDays(2))->create();

    $metrics = deliveryMetrics(delivery($this->repository, $this->calendar), $this->inWindow, 7);

    // The first deployment only marks a boundary; the next two ship 2 and 1 pull requests.
    expect($metrics['deployments']['value'])->toBe(2)
        ->and($metrics['deployFrequency']['value'])->toBe(0.29)
        ->and($metrics['leadTimeToProduction']['value'])->toEqual(30 * 3600)
        ->and($metrics['changeFailureRate']['value'])->toEqual(0);
});

it('treats a deployment followed by a revert as a failed change', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create();
    mergedPullRequest($this->repository, $this->now->subDays(5));
    Deployment::factory()->for($this->repository)->at($this->now->subDays(5)->addHour())->create();
    mergedPullRequest($this->repository, $this->now->subDays(4), ['title' => 'Revert "Add caching"']);
    Deployment::factory()->for($this->repository)->at($this->now->subDays(4)->addHours(3))->create();

    $metrics = deliveryMetrics(delivery($this->repository, $this->calendar), $this->inWindow, 7);

    expect($metrics['changeFailureRate']['value'])->toEqual(0.5)
        ->and($metrics['timeToRestore']['value'])->toEqual(26 * 3600);
});

it('keeps release lines of different branches apart', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create(['ref' => 'main']);
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create(['ref' => '1.x']);
    mergedPullRequest($this->repository, $this->now->subDays(5), ['base_ref' => '1.x']);
    Deployment::factory()->for($this->repository)->at($this->now->subDays(4))->create(['ref' => 'main']);
    Deployment::factory()->for($this->repository)->at($this->now->subDays(3))->create(['ref' => '1.x']);

    $recent = collect(delivery($this->repository, $this->calendar)->recent())->pluck('shipped', 'ref');

    expect($recent->all())->toBe(['1.x' => 1, 'main' => 0]);
});

it('leaves pull requests merged while a deployment rolled out to the next one', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(6))->create(['name' => 'v1']);
    mergedPullRequest($this->repository, $this->now->subDays(5));
    Deployment::factory()->for($this->repository)->at($this->now->subDays(4))->create(['name' => 'v2', 'committed_at' => $this->now->subDays(5)->addHour()]);
    mergedPullRequest($this->repository, $this->now->subDays(4)->subHour());
    Deployment::factory()->for($this->repository)->at($this->now->subDays(3))->create(['name' => 'v3']);

    $recent = collect(delivery($this->repository, $this->calendar)->recent())->pluck('shipped', 'name');

    expect($recent->all())->toBe(['v3' => 1, 'v2' => 1]);
});

it('counts merged work that has not shipped yet', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(3))->create();
    mergedPullRequest($this->repository, $this->now->subDays(4));
    mergedPullRequest($this->repository, $this->now->subDays(2));
    mergedPullRequest($this->repository, $this->now->subDay());

    expect(delivery($this->repository, $this->calendar)->pending($this->now))->toBe(['count' => 2, 'oldestSeconds' => 2 * 86400]);
});

it('reports failed deployment attempts for GitHub deployments', function () {
    Deployment::factory()->for($this->repository)->at($this->now->subDays(3))->create(['source' => DeploymentSource::Deployments]);
    Deployment::factory()->for($this->repository)->failed()->at($this->now->subDays(2))->create();

    expect(deliveryMetrics(delivery($this->repository, $this->calendar), $this->inWindow, 7)['deploymentFailureRate']['value'])->toEqual(0.5);
});

it('reports nothing when the repository has no deploy signal', function () {
    mergedPullRequest($this->repository, $this->now->subDays(2));

    $metrics = deliveryMetrics(delivery($this->repository, $this->calendar), $this->inWindow, 7);

    expect($metrics['deployments']['value'])->toBeNull()
        ->and($metrics['leadTimeToProduction']['value'])->toBeNull();
});
