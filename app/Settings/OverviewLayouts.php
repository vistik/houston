<?php

namespace App\Settings;

use App\Enums\OverviewLayout;
use App\Models\Setting;

/**
 * How the overviews lay out teams and repositories, shared by the front page and the teams and
 * repositories pages.
 */
readonly class OverviewLayouts
{
    private const string Key = 'overview_layouts';

    public function __construct(
        public OverviewLayout $teams = OverviewLayout::Cards,
        public OverviewLayout $repositories = OverviewLayout::Cards,
    ) {}

    /**
     * The saved layouts, falling back to cards for anything unset or no longer known.
     */
    public static function current(): self
    {
        $value = Setting::get(self::Key, []);

        return new self(
            OverviewLayout::tryFrom((string) ($value['teams'] ?? '')) ?? OverviewLayout::Cards,
            OverviewLayout::tryFrom((string) ($value['repositories'] ?? '')) ?? OverviewLayout::Cards,
        );
    }

    public function save(): void
    {
        Setting::put(self::Key, $this->toArray());
    }

    /**
     * @return array{teams: string, repositories: string}
     */
    public function toArray(): array
    {
        return ['teams' => $this->teams->value, 'repositories' => $this->repositories->value];
    }
}
