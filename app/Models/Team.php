<?php

namespace App\Models;

use App\Enums\TeamCardView;
use App\Support\Demo;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A group of people whose pull requests are reported on together, across every watched repository.
 * Who was on it changes over time; see TeamMembership.
 *
 * @property string|null $card_view
 */
#[Fillable(['name', 'slug', 'card_view'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * What this team's card shows, falling back to the full card when none was picked or the picked one
     * is no longer known.
     */
    public function cardView(): TeamCardView
    {
        return TeamCardView::tryFrom((string) Demo::preference("teams.{$this->id}.card_view", $this->card_view)) ?? TeamCardView::Full;
    }

    /**
     * @return HasMany<TeamMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * A URL slug for the name that no other team uses yet.
     */
    public static function uniqueSlug(string $name, ?self $except = null): string
    {
        $base = Str::slug($name) ?: 'team';
        $slug = $base;

        for ($suffix = 2; self::query()->where('slug', $slug)->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * When each GitHub login was on the team, as unix timestamps, keyed by the login in lower case. A
     * null bound is open: always before, or for good after.
     *
     * @return array<string, list<array{from: int|null, until: int|null}>>
     */
    public function spans(): array
    {
        $spans = [];

        foreach ($this->memberships as $membership) {
            $spans[Str::lower($membership->login)][] = [
                'from' => $membership->joined_on?->getTimestamp(),
                'until' => $membership->left_on?->getTimestamp(),
            ];
        }

        return $spans;
    }
}
