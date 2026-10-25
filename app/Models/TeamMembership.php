<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TeamMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A GitHub login on a team from the day it joined up to the day it left. Without a joined day it was
 * on the team for as far back as reports go, and without a left day it still is. A login is on one
 * team at a time, so its memberships never overlap.
 *
 * @property string $login
 * @property CarbonImmutable|null $joined_on
 * @property CarbonImmutable|null $left_on
 */
#[Fillable(['team_id', 'login', 'joined_on', 'left_on'])]
class TeamMembership extends Model
{
    /** @use HasFactory<TeamMembershipFactory> */
    use HasFactory;

    /**
     * Reports cache per team until it changes, so a change to who was on it moves the team on too.
     *
     * @var list<string>
     */
    protected $touches = ['team'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => 'immutable_date',
            'left_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Memberships of the login, in any team, that share at least one day with the given span. GitHub
     * logins are case-insensitive.
     *
     * @return Builder<self>
     */
    public static function overlapping(string $login, ?CarbonImmutable $from, ?CarbonImmutable $until): Builder
    {
        return self::query()
            ->whereRaw('lower(login) = ?', [Str::lower($login)])
            ->when($until !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhere('joined_on', '<', $until)))
            ->when($from !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('left_on')->orWhere('left_on', '>', $from)));
    }

    /**
     * When the membership runs, to follow a team name, e.g. " since Oct 1, 2026", or nothing when it always has.
     */
    public function describeSpan(): string
    {
        return match (true) {
            $this->joined_on !== null && $this->left_on !== null => " from {$this->joined_on->toFormattedDateString()} until {$this->left_on->toFormattedDateString()}",
            $this->joined_on !== null => " since {$this->joined_on->toFormattedDateString()}",
            $this->left_on !== null => " until {$this->left_on->toFormattedDateString()}",
            default => '',
        };
    }

    public function isCurrent(): bool
    {
        $today = CarbonImmutable::today();

        return ($this->joined_on === null || $this->joined_on->lte($today)) && ($this->left_on === null || $this->left_on->gt($today));
    }
}
