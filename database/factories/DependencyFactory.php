<?php

namespace Database\Factories;

use App\Enums\DependencyDrift;
use App\Enums\DependencyEcosystem;
use App\Models\Dependency;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dependency>
 */
class DependencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'ecosystem' => DependencyEcosystem::Composer,
            'name' => fake()->unique()->userName().'/'.fake()->slug(2),
            'constraint' => '^1.0',
            'version' => '1.0.0',
            'latest_version' => '1.0.0',
            'is_dev' => false,
            'drift' => DependencyDrift::Current,
        ];
    }

    /**
     * Installed at the given version, behind the given latest release.
     */
    public function behind(string $version, string $latestVersion): static
    {
        return $this->state([
            'version' => $version,
            'latest_version' => $latestVersion,
            'drift' => DependencyDrift::between($version, $latestVersion),
        ]);
    }
}
