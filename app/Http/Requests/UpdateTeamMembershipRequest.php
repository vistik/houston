<?php

namespace App\Http\Requests;

use App\Models\TeamMembership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeamMembershipRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'joined_on' => ['nullable', 'date_format:Y-m-d'],
            'left_on' => ['nullable', 'date_format:Y-m-d', ...($this->filled('joined_on') ? ['after:joined_on'] : [])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'left_on.after' => 'The day they left has to be after the day they joined.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var TeamMembership $membership */
                $membership = $this->route('membership');

                $conflict = TeamMembership::overlapping($membership->login, $this->day('joined_on'), $this->day('left_on'))
                    ->with('team')
                    ->whereKeyNot($membership->id)
                    ->first();

                if ($conflict !== null) {
                    $validator->errors()->add('joined_on', "{$membership->login} is on {$conflict->team->name}{$conflict->describeSpan()}, which overlaps.");
                }
            },
        ];
    }

    public function day(string $key): ?CarbonImmutable
    {
        return $this->filled($key) ? CarbonImmutable::parse($this->string($key)->toString())->startOfDay() : null;
    }
}
