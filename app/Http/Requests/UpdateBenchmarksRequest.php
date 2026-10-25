<?php

namespace App\Http\Requests;

use App\Metrics\Benchmarks;
use App\Metrics\Better;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBenchmarksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'benchmarks' => ['present', 'array:'.implode(',', Benchmarks::Metrics)],
            'benchmarks.*' => ['array:direction,elite,good,medium'],
            'benchmarks.*.direction' => ['required', Rule::enum(Better::class)->except(Better::Neutral)],
            'benchmarks.*.elite' => ['required', 'numeric', 'min:0'],
            'benchmarks.*.good' => ['required', 'numeric', 'min:0'],
            'benchmarks.*.medium' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'benchmarks.*.*.required' => 'Set all three thresholds.',
            'benchmarks.*.*.numeric' => 'Use a number.',
            'benchmarks.*.*.min' => 'Use a positive number.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->benchmarks() as $metric => ['direction' => $direction, 'elite' => $elite, 'good' => $good, 'medium' => $medium]) {
                    $ordered = $direction === Better::Higher
                        ? $elite >= $good && $good >= $medium
                        : $elite <= $good && $good <= $medium;

                    if (! $ordered) {
                        $validator->errors()->add("benchmarks.{$metric}", $direction === Better::Higher
                            ? 'Elite must be at least good, and good at least medium.'
                            : 'Elite must be at most good, and good at most medium.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, array{direction: Better, elite: float, good: float, medium: float}>
     */
    public function benchmarks(): array
    {
        return array_map(fn (array $benchmark): array => [
            'direction' => Better::from($benchmark['direction']),
            'elite' => (float) $benchmark['elite'],
            'good' => (float) $benchmark['good'],
            'medium' => (float) $benchmark['medium'],
        ], $this->array('benchmarks'));
    }
}
