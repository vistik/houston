<?php

namespace App\Http\Requests;

use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLookbackRequest extends FormRequest
{
    /**
     * Lookbacks to choose from, in days. Reports compare against the window before, so a lookback should
     * be at least twice the longest period looked at. All time caches the whole history of the repository.
     *
     * @var list<int>
     */
    public const array Options = [30, 90, 180, 365, 730, Repository::AllTime];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lookback_days' => ['present', 'nullable', 'integer', Rule::in(self::Options)],
        ];
    }
}
