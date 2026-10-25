<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'login' => fake()->unique()->userName(),
            'joined_on' => null,
            'left_on' => null,
        ];
    }
}
