<?php

namespace Database\Factories;

use App\Enums\RepositorySyncStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner' => fake()->unique()->userName(),
            'name' => fake()->unique()->slug(2),
        ];
    }

    /**
     * A repository whose cache was freshly synced.
     */
    public function synced(string $defaultBranch = 'main'): static
    {
        return $this->afterCreating(function (Repository $repository) use ($defaultBranch): void {
            $repository->sync()->create([
                'status' => RepositorySyncStatus::Succeeded,
                'default_branch' => $defaultBranch,
                'pull_requests_synced_at' => now(),
                'workflow_runs_synced_at' => now(),
                'finished_at' => now(),
            ]);
        });
    }
}
