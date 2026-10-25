<?php

namespace Database\Factories;

use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimelineEvent>
 */
class TimelineEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Introduced '.fake()->word(),
            'happened_on' => fake()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
        ];
    }
}
