<?php

namespace Database\Factories;

use App\Enums\WorkflowRunConclusion;
use App\Enums\WorkflowRunStatus;
use App\Models\Repository;
use App\Models\WorkflowRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = CarbonImmutable::now()->subDays(fake()->numberBetween(1, 20));

        return [
            'repository_id' => Repository::factory(),
            'github_id' => fake()->unique()->numberBetween(1, PHP_INT_MAX),
            'workflow_id' => 1,
            'name' => 'tests',
            'event' => 'push',
            'status' => WorkflowRunStatus::Completed,
            'conclusion' => WorkflowRunConclusion::Success,
            'head_branch' => 'main',
            'head_sha' => fake()->sha1(),
            'run_attempt' => 1,
            'url' => fake()->url(),
            'run_started_at' => $createdAt,
            'completed_at' => $createdAt->addMinutes(5),
            'duration_seconds' => 300,
            'created_at' => $createdAt,
        ];
    }

    public function failed(): static
    {
        return $this->state(['conclusion' => WorkflowRunConclusion::Failure]);
    }

    public function at(CarbonImmutable $createdAt, int $durationMinutes = 5): static
    {
        return $this->state([
            'created_at' => $createdAt,
            'run_started_at' => $createdAt,
            'completed_at' => $createdAt->addMinutes($durationMinutes),
            'duration_seconds' => $durationMinutes * 60,
        ]);
    }
}
