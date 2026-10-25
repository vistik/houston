import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Panel } from '@/components/ui/panel';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Select } from '@/components/ui/select';
import SettingsLayout from '@/layouts/settings-layout';
import { fitsPeriod, GRANULARITY_OPTIONS, PERIOD_OPTIONS } from '@/lib/periods';
import type { Granularity } from '@/lib/periods';
import type { CiFailureRateScope } from '@/types';
import { update } from '@/routes/settings/defaults';
import { buttonClass } from '@/components/ui/button';

type Defaults = {
    days: number | null;
    granularity: Granularity;
    withoutCurrentBucket: boolean;
    ciFailureRateScope: CiFailureRateScope;
    timezone: string;
};

const ALL = 'all';

const CI_FAILURE_RATE_SCOPES: { value: CiFailureRateScope; label: string }[] = [
    { value: 'default_branch', label: 'Default branch' },
    { value: 'all_branches', label: 'All branches' },
];

export default function ReportDefaults({
    defaults,
    timezones,
}: {
    defaults: Defaults;
    timezones: string[];
}) {
    const form = useForm<Defaults>(defaults);
    const {
        days,
        granularity,
        withoutCurrentBucket,
        ciFailureRateScope,
        timezone,
    } = form.data;
    const granularityDays = GRANULARITY_OPTIONS.find(
        (option) => option.value === granularity,
    )!.days;

    return (
        <SettingsLayout>
            <Head title="Defaults" />

            <p className="max-w-2xl text-sm text-ink-secondary">
                The period and grouping the overview and repository reports open
                on, and whether their charts leave out the current {granularity}
                . Picking another one on a report still works, for that visit.
                The timezone and CI failure rate settings apply to every report.
            </p>

            <Panel
                title="Reports"
                className="mt-8"
                bodyClassName="px-5 pb-2 pt-1 divide-y divide-line"
            >
                <Row label="Reporting period" hint="How far back reports look.">
                    <Select
                        label="Default reporting period"
                        value={days === null ? ALL : String(days)}
                        onChange={(next) =>
                            form.setData(
                                'days',
                                next === ALL ? null : Number(next),
                            )
                        }
                        options={[
                            ...PERIOD_OPTIONS.map((option) => ({
                                value: String(option.value),
                                label: option.label,
                            })),
                            { value: ALL, label: 'All time' },
                        ]}
                    />
                </Row>
                <Row
                    label="Group by"
                    hint="How charts bucket the period."
                    note={
                        days !== null && !fitsPeriod(granularityDays, days)
                            ? `Too few ${granularity}s in this period to chart a trend, so reports pick a grouping themselves.`
                            : undefined
                    }
                >
                    <SegmentedControl
                        label="Default grouping"
                        options={GRANULARITY_OPTIONS.map((option) => ({
                            value: option.value,
                            label: option.label,
                        }))}
                        value={granularity}
                        onChange={(next) => form.setData('granularity', next)}
                    />
                </Row>
                <Row
                    label={`Exclude current ${granularity}`}
                    hint={`Leave the ${granularity} still in progress out of the charts, so its incomplete data doesn't read as a drop.`}
                >
                    <input
                        type="checkbox"
                        aria-label={`Exclude the current ${granularity} by default`}
                        checked={withoutCurrentBucket}
                        onChange={(event) =>
                            form.setData(
                                'withoutCurrentBucket',
                                event.target.checked,
                            )
                        }
                        className="size-4 cursor-pointer rounded accent-[var(--accent)]"
                    />
                </Row>
                <Row
                    label="Timezone"
                    hint="Where days start and end, for daily charts, custom ranges and working hours. Everyone sees reports on this clock."
                >
                    <Select
                        label="Timezone reports are shown in"
                        search
                        value={timezone}
                        onChange={(next) => form.setData('timezone', next)}
                        options={timezones.map((zone) => ({
                            value: zone,
                            label: zone.replaceAll('_', ' '),
                        }))}
                    />
                </Row>
                <Row
                    label="CI failure rate counts"
                    hint="Which workflow runs the headline CI failure rate and its trend count. The default branch leaves out pull requests and work in progress on other branches."
                >
                    <SegmentedControl
                        label="Runs the CI failure rate counts"
                        options={CI_FAILURE_RATE_SCOPES}
                        value={ciFailureRateScope}
                        onChange={(next) =>
                            form.setData('ciFailureRateScope', next)
                        }
                    />
                </Row>
            </Panel>

            <div className="mt-4 flex items-center justify-end gap-4">
                <span className="text-xs text-ink-muted">
                    {form.recentlySuccessful
                        ? 'Saved.'
                        : form.isDirty
                          ? 'Unsaved changes'
                          : ''}
                </span>
                <button
                    type="button"
                    onClick={() =>
                        form.submit(update(), { preserveScroll: true })
                    }
                    disabled={form.processing || !form.isDirty}
                    className={buttonClass({ variant: 'primary', size: 'md' })}
                >
                    {form.processing ? 'Saving…' : 'Save defaults'}
                </button>
            </div>
        </SettingsLayout>
    );
}

function Row({
    label,
    hint,
    note,
    children,
}: {
    label: string;
    hint: string;
    note?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
            <div className="min-w-0">
                <div className="text-sm text-ink">{label}</div>
                <div className="text-[11px] text-ink-muted">{hint}</div>
                {note && (
                    <div className="mt-1 text-[11px] text-warn">{note}</div>
                )}
            </div>
            <div className="shrink-0">{children}</div>
        </div>
    );
}
