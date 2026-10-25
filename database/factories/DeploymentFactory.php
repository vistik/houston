<?php

namespace Database\Factories;

use App\Enums\DeploymentSource;
use App\Enums\DeploymentState;
use App\Models\Deployment;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deployment>
 */
class DeploymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'source' => DeploymentSource::Releases,
            'external_id' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'name' => 'v'.fake()->semver(),
            'ref' => 'main',
            'state' => DeploymentState::Success,
            'deployed_at' => CarbonImmutable::now()->subDays(fake()->numberBetween(1, 20)),
        ];
    }

    public function at(CarbonImmutable $deployedAt): static
    {
        return $this->state(['deployed_at' => $deployedAt]);
    }

    public function failed(): static
    {
        return $this->state(['source' => DeploymentSource::Deployments, 'state' => DeploymentState::Failure]);
    }
}
