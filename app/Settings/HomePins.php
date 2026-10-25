<?php

namespace App\Settings;

use App\Models\Repository;
use App\Models\Setting;
use App\Models\Team;

/**
 * The teams and repositories picked to show on the front page. For each kind, null shows all of them,
 * including ones added later, a list shows just those, and an empty list hides that section.
 */
readonly class HomePins
{
    private const string Key = 'home_pins';

    /**
     * @param  list<int>|null  $teamIds
     * @param  list<int>|null  $repositoryIds
     */
    public function __construct(
        public ?array $teamIds = null,
        public ?array $repositoryIds = null,
    ) {}

    /**
     * The saved picks, leaving out teams and repositories that no longer exist.
     */
    public static function current(): self
    {
        $value = Setting::get(self::Key, []);

        return new self(
            self::existing(Team::class, $value['teams'] ?? null),
            self::existing(Repository::class, $value['repositories'] ?? null),
        );
    }

    public function save(): void
    {
        Setting::put(self::Key, $this->toArray());
    }

    public function showsAllTeams(): bool
    {
        return $this->teamIds === null;
    }

    public function showsAllRepositories(): bool
    {
        return $this->repositoryIds === null;
    }

    /**
     * @return array{teams: list<int>|null, repositories: list<int>|null}
     */
    public function toArray(): array
    {
        return ['teams' => $this->teamIds, 'repositories' => $this->repositoryIds];
    }

    /**
     * Picks that have all since been deleted fall back to showing all, rather than hiding the section
     * without anyone asking for that.
     *
     * @param  class-string<Team|Repository>  $model
     * @return list<int>|null
     */
    private static function existing(string $model, mixed $ids): ?array
    {
        if (! is_array($ids)) {
            return null;
        }

        if ($ids === []) {
            return [];
        }

        $existing = array_values($model::query()->whereKey($ids)->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

        return $existing === [] ? null : $existing;
    }
}
