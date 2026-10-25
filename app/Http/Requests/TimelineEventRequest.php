<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class TimelineEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'happened_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'happened_on.before_or_equal' => 'Pick a day that has already happened.',
        ];
    }

    /**
     * @return array{title: string, happened_on: CarbonImmutable}
     */
    public function event(): array
    {
        return [
            'title' => trim($this->string('title')->toString()),
            'happened_on' => CarbonImmutable::createFromFormat('!Y-m-d', $this->string('happened_on')->toString()),
        ];
    }
}
