<?php

use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;

it('parses an immutable date attribute as Eloquent would', function () {
    $run = WorkflowRun::factory()->create(['created_at' => '2026-03-02 10:00:00'])->fresh();

    expect($run->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($run->created_at->toDateTimeString())->toBe('2026-03-02 10:00:00')
        ->and($run->created_at->timezoneName)->toBe(date_default_timezone_get());
});

it('does not keep the dates it parses', function () {
    $run = WorkflowRun::factory()->create(['created_at' => '2026-03-02 10:00:00'])->fresh();

    expect($run->created_at)->not->toBe($run->created_at)
        ->and($run->created_at)->toEqual($run->created_at);
});

it('parses the date again after it changes', function () {
    $run = WorkflowRun::factory()->create(['created_at' => '2026-03-02 10:00:00'])->fresh();
    $before = $run->created_at;

    $run->created_at = CarbonImmutable::parse('2026-03-05 12:30:00');

    expect($run->created_at->toDateTimeString())->toBe('2026-03-05 12:30:00')
        ->and($before->toDateTimeString())->toBe('2026-03-02 10:00:00');
});

it('leaves a date in another format to Eloquent', function () {
    $run = (new WorkflowRun)->setRawAttributes(['created_at' => '2026-03-02T10:00:00+00:00', 'completed_at' => '1772445600']);

    expect($run->created_at->toDateTimeString())->toBe('2026-03-02 10:00:00')
        ->and($run->completed_at->toDateTimeString())->toBe('2026-03-02 10:00:00');
});
