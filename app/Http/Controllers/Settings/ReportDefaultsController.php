<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventType;
use App\Http\Controllers\Controller;
use App\Metrics\CiFailureRateScope;
use App\Metrics\Granularity;
use App\Metrics\Period;
use App\Models\SystemEvent;
use App\Settings\ReportDefaults;
use Closure;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportDefaultsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/defaults', [
            'defaults' => ReportDefaults::current()->toArray(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', Rule::in(Period::AllowedDays)],
            'granularity' => ['required', Rule::enum(Granularity::class)],
            'withoutCurrentBucket' => ['required', 'boolean'],
            'ciFailureRateScope' => ['required', Rule::enum(CiFailureRateScope::class)],
            // A page opened before the timezone could be picked sends none, so keep the current one.
            'timezone' => ['sometimes', 'string', function (string $attribute, string $value, Closure $fail): void {
                if (ReportDefaults::validTimezone($value) === null) {
                    $fail('Pick a timezone from the list.');
                }
            }],
        ]);

        $defaults = new ReportDefaults(
            isset($validated['days']) ? (int) $validated['days'] : null,
            Granularity::from($validated['granularity']),
            (bool) $validated['withoutCurrentBucket'],
            CiFailureRateScope::from($validated['ciFailureRateScope']),
            ReportDefaults::validTimezone($validated['timezone'] ?? null) ?? ReportDefaults::timezone(),
        );
        $defaults->save();

        SystemEvent::record(SystemEventType::SettingsDefaults, 'Updated the report defaults', context: $defaults->toArray());

        return to_route('settings.defaults.edit');
    }
}
