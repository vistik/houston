<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBenchmarksRequest;
use App\Metrics\Benchmarks;
use App\Models\Benchmark;
use App\Models\SystemEvent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BenchmarkController extends Controller
{
    public function __construct(private Benchmarks $benchmarks) {}

    public function edit(): Response
    {
        return Inertia::render('settings/benchmarks', [
            'benchmarks' => $this->benchmarks->all()->map(fn (Benchmark $benchmark): array => [
                'direction' => $benchmark->direction,
                'elite' => $benchmark->elite,
                'good' => $benchmark->good,
                'medium' => $benchmark->medium,
            ]),
            'standards' => Benchmarks::Standards,
            'perWeek' => Benchmarks::PerWeek,
        ]);
    }

    public function update(UpdateBenchmarksRequest $request): RedirectResponse
    {
        $this->benchmarks->replace($request->benchmarks());

        SystemEvent::record(SystemEventType::SettingsBenchmarks, 'Updated the benchmarks');

        return to_route('settings.benchmarks.edit');
    }
}
