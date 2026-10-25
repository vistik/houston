<?php

namespace Database\Factories;

use App\Enums\PullRequestState;
use App\Models\PullRequest;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PullRequest>
 */
class PullRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openedAt = CarbonImmutable::now()->subDays(fake()->numberBetween(1, 20));

        return [
            'repository_id' => Repository::factory(),
            'number' => fake()->unique()->numberBetween(1, 1_000_000),
            'title' => fake()->sentence(),
            'url' => fake()->url(),
            'state' => PullRequestState::Open,
            'is_draft' => false,
            'author' => fake()->userName(),
            'author_is_bot' => false,
            'base_ref' => 'main',
            'head_ref' => fake()->slug(2),
            'additions' => fake()->numberBetween(1, 200),
            'deletions' => fake()->numberBetween(0, 100),
            'changed_files' => fake()->numberBetween(1, 10),
            'opened_at' => $openedAt,
            'ready_at' => $openedAt,
            'last_activity_at' => $openedAt,
        ];
    }

    /**
     * Shape a pull request's lifecycle relative to when it was opened, in hours.
     */
    public function lifecycle(CarbonImmutable $openedAt, ?float $respondedAfter = null, ?float $approvedAfter = null, ?float $mergedAfter = null): static
    {
        $at = fn (?float $hours): ?CarbonImmutable => $hours === null ? null : $openedAt->addMinutes((int) round($hours * 60));

        return $this->state([
            'opened_at' => $openedAt,
            'ready_at' => $openedAt,
            'first_commit_at' => $openedAt->subHour(),
            'first_response_at' => $at($respondedAfter),
            'first_review_at' => $at($respondedAfter),
            'first_approval_at' => $at($approvedAfter),
            'merged_at' => $at($mergedAfter),
            'closed_at' => $at($mergedAfter),
            'state' => $mergedAfter === null ? PullRequestState::Open : PullRequestState::Merged,
            'last_activity_at' => $at($mergedAfter) ?? $at($approvedAfter) ?? $at($respondedAfter) ?? $openedAt,
        ]);
    }

    public function bot(): static
    {
        return $this->state(['author' => 'dependabot[bot]', 'author_is_bot' => true]);
    }
}
