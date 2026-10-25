<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PullRequest;
use App\Models\Team;
use App\Models\TeamMembership;
use Illuminate\Support\Str;

trait PresentsTeams
{
    /**
     * @return array{id: int, name: string, slug: string, memberships: array<int, array{id: int, login: string, avatarUrl: string|null, joinedOn: string|null, leftOn: string|null, current: bool}>}
     */
    private function teamPayload(Team $team): array
    {
        $memberships = $team->memberships->sortBy([
            fn (TeamMembership $a, TeamMembership $b): int => Str::lower($a->login) <=> Str::lower($b->login),
            fn (TeamMembership $a, TeamMembership $b): int => $a->joined_on <=> $b->joined_on,
        ]);
        $avatars = PullRequest::avatarUrls($memberships->map(fn (TeamMembership $membership): string => $membership->login)->values()->all());

        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'cardView' => $team->cardView()->value,
            'memberships' => $memberships->map(fn (TeamMembership $membership): array => [
                'id' => $membership->id,
                'login' => $membership->login,
                'avatarUrl' => $avatars[Str::lower($membership->login)] ?? null,
                'joinedOn' => $membership->joined_on?->toDateString(),
                'leftOn' => $membership->left_on?->toDateString(),
                'current' => $membership->isCurrent(),
            ])->values()->all(),
        ];
    }
}
