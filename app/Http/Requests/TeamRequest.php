<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('teams', 'name')->ignore($team instanceof Team ? $team->id : null)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'There is a team with this name already.',
        ];
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }
}
