<?php

use App\Metrics\Period;
use App\Models\Repository;
use App\Models\Team;
use App\Models\TimelineEvent;
use App\Reports\VelocityReport;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-24 12:00:00');
    $this->seed(DemoSeeder::class);
});

it('seeds repositories, teams and the timeline events', function () {
    expect(Repository::query()->get()->map->fullName()->all())->toBe(['acme/api', 'acme/web', 'acme/mobile'])
        ->and(Team::query()->pluck('name')->all())->toBe(['Platform', 'Product'])
        ->and(TimelineEvent::query()->orderBy('happened_on')->pluck('title')->all())->toBe([
            'Switched to Claude Team',
            'Introduced AI code reviews',
            'Deployed Slack bot for GitHub review reminders',
        ]);
});

it('has a few jobs that are clearly the flakiest', function () {
    $repository = Repository::query()->firstWhere('name', 'api');
    $flaky = (new VelocityReport($repository, Period::lastDays(90, 'UTC')))->toArray()['flakyJobs'];

    expect($flaky['jobs'][0]['name'])->toBe('browser (chrome)')
        ->and($flaky['jobs'][0]['steps'][0]['name'])->toBe('Run browser tests')
        ->and(collect($flaky['jobs'])->pluck('steps')->flatten(1)->where('setup', true))->not->toBeEmpty()
        ->and($flaky['seconds'])->toBeGreaterThan(3600)
        ->and($flaky['waitSeconds'])->toBeGreaterThan($flaky['seconds'])
        ->and($flaky['pendingRuns'])->toBe(0);
});

it('makes introducing AI code reviews a clear improvement', function () {
    $event = TimelineEvent::query()->where('title', 'Introduced AI code reviews')->sole();

    foreach (Repository::all() as $repository) {
        $kpis = (new VelocityReport($repository, Period::between($event->happened_on, CarbonImmutable::today(), 'UTC', $repository->lookbackDays())))->toArray()['kpis'];

        expect($kpis['throughput']['value'])->toBeGreaterThan($kpis['throughput']['previous'] * 1.3)
            ->and($kpis['cycleTime']['value'])->toBeLessThan($kpis['cycleTime']['previous'] * 0.6)
            ->and($kpis['reviewRounds']['value'])->toBeLessThan($kpis['reviewRounds']['previous'])
            ->and($kpis['ciFailureRate']['value'])->toBeLessThan($kpis['ciFailureRate']['previous'])
            ->and($kpis['changeFailureRate']['value'])->toBeLessThan($kpis['changeFailureRate']['previous']);
    }
});
