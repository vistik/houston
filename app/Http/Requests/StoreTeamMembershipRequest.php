<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\TeamMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTeamMembershipRequest extends FormRequest
{
    /**
     * A GitHub login, optionally with a leading @.
     */
    public const string LoginPattern = '/^@?[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:40', 'regex:'.self::LoginPattern],
            'joined_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'Use a GitHub username, e.g. taylorotwell.',
        ];
    }

    /**
     * A login is on one team at a time. Joining a team from a given day ends the membership it has
     * until then, so moving someone only takes the day they moved; any other overlap is refused.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Team $team */
                $team = $this->route('team');
                $ending = $this->membershipToEnd();

                $conflict = $ending?->team_id === $team->id ? $ending : TeamMembership::overlapping($this->login(), $this->joinedOn(), null)
                    ->with('team')
                    ->when($ending, fn (Builder $query) => $query->whereKeyNot($ending?->id))
                    ->first();

                if ($conflict !== null) {
                    $validator->errors()->add('joined_on', $conflict->team_id === $team->id
                        ? "{$this->login()} is already on {$team->name}{$conflict->describeSpan()}."
                        : "{$this->login()} is on {$conflict->team->name}{$conflict->describeSpan()}. Pick a later day, or change that membership first.");
                }
            },
        ];
    }

    public function login(): string
    {
        return ltrim(trim($this->string('login')->toString()), '@');
    }

    public function joinedOn(): ?CarbonImmutable
    {
        return $this->filled('joined_on') ? CarbonImmutable::parse($this->string('joined_on')->toString())->startOfDay() : null;
    }

    /**
     * The ongoing membership that joining ends, when the login was already on a team before the day it joins.
     */
    public function membershipToEnd(): ?TeamMembership
    {
        $joinedOn = $this->joinedOn();

        if ($joinedOn === null) {
            return null;
        }

        return TeamMembership::overlapping($this->login(), $joinedOn, null)
            ->whereNull('left_on')
            ->where(fn (Builder $query) => $query->whereNull('joined_on')->orWhere('joined_on', '<', $joinedOn))
            ->first();
    }
}
