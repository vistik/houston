<?php

use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;

it('parses an immutable date attribute once', function () {
    $run = WorkflowRun::factory()->create(['created_at' => '2026-03-02 10:00:00'])->fresh();

    expect($run->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($run->created_at)->toBe($run->created_at);
});

it('parses the date again after it changes', function () {
    $run = WorkflowRun::factory()->create(['created_at' => '2026-03-02 10:00:00'])->fresh();
    $before = $run->created_at;

    $run->created_at = CarbonImmutable::parse('2026-03-05 12:30:00');

    expect($run->created_at->toDateTimeString())->toBe('2026-03-05 12:30:00')
        ->and($before->toDateTimeString())->toBe('2026-03-02 10:00:00');
});
