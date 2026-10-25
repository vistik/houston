import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { TIER_TONES } from '@/components/metric-card';
import { Panel } from '@/components/ui/panel';
import { Select } from '@/components/ui/select';
import SettingsLayout from '@/layouts/settings-layout';
import {
    METRIC_GROUPS,
    TIER_LABELS,
    describeThresholds,
    fromInput,
    inputSuffix,
    toInput,
} from '@/lib/benchmarks';
import type { MetricInfo } from '@/lib/benchmarks';
import { cn } from '@/lib/utils';
import { update } from '@/routes/settings/benchmarks';
import type { MetricKey, Thresholds } from '@/types';
import { buttonClass } from '@/components/ui/button';

type Standard = Thresholds & { source: string };

type Props = {
    benchmarks: Partial<Record<MetricKey, Thresholds>>;
    standards: Partial<Record<MetricKey, Standard>>;
    perWeek: MetricKey[];
};

type Draft = {
    direction: Thresholds['direction'];
    elite: string;
    good: string;
    medium: string;
};

type Drafts = Partial<Record<MetricKey, Draft>>;

const LEVELS = ['elite', 'good', 'medium'] as const;

const METRICS = METRIC_GROUPS.flatMap(
    (group) => Object.entries(group.metrics) as [MetricKey, MetricInfo][],
);

export default function Benchmarks({ benchmarks, standards, perWeek }: Props) {
    const weekly = (key: MetricKey) => perWeek.includes(key);
    const info = (key: MetricKey) =>
        METRICS.find(([candidate]) => candidate === key)![1];

    const toDraft = (key: MetricKey, thresholds: Thresholds): Draft => ({
        direction: thresholds.direction,
        ...(Object.fromEntries(
            LEVELS.map((level) => [
                level,
                toInput(thresholds[level], info(key).unit, weekly(key)),
            ]),
        ) as Pick<Draft, (typeof LEVELS)[number]>),
    });

    const form = useForm<{ benchmarks: Drafts }>({
        benchmarks: Object.fromEntries(
            Object.entries({ ...benchmarks }).map(([key, thresholds]) => [
                key,
                toDraft(key as MetricKey, thresholds),
            ]),
        ),
    });
    const [unreadable, setUnreadable] = useState<Set<string>>(new Set());

    const drafts = form.data.benchmarks;
    const setDraft = (key: MetricKey, draft: Draft | undefined) => {
        const next = { ...drafts };

        if (draft) {
            next[key] = draft;
        } else {
            delete next[key];
        }

        form.setData('benchmarks', next);
    };

    const missingStandards = METRICS.filter(
        ([key]) => standards[key] && !drafts[key],
    );

    const addStandards = () =>
        form.setData('benchmarks', {
            ...drafts,
            ...Object.fromEntries(
                missingStandards.map(([key]) => [
                    key,
                    toDraft(key, standards[key]!),
                ]),
            ),
        });

    const submit = () => {
        const invalid = new Set<string>();
        const payload: Partial<Record<MetricKey, Thresholds>> = {};

        for (const [key, draft] of Object.entries(drafts) as [
            MetricKey,
            Draft,
        ][]) {
            const values = LEVELS.map((level) => {
                const value = fromInput(draft[level], info(key).unit);

                if (value === null) {
                    invalid.add(`${key}.${level}`);
                }

                return value ?? 0;
            });

            payload[key] = {
                direction: draft.direction,
                elite: values[0],
                good: values[1],
                medium: values[2],
            };
        }

        setUnreadable(invalid);

        if (invalid.size > 0) {
            return;
        }

        form.transform(() => ({ benchmarks: payload }));
        form.submit(update(), { preserveScroll: true });
    };

    const count = Object.keys(drafts).length;

    return (
        <SettingsLayout>
            <Head title="Benchmarks" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="max-w-2xl text-sm text-ink-secondary">
                        Set targets for any metric and Houston rates it as{' '}
                        <TierChip tier="elite" />, <TierChip tier="good" />,{' '}
                        <TierChip tier="medium" /> or <TierChip tier="low" />{' '}
                        wherever it appears. Counts of work are benchmarked per
                        week, so targets hold for every reporting period.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {missingStandards.length > 0 && (
                        <button
                            type="button"
                            onClick={addStandards}
                            className={buttonClass({
                                variant: 'secondary',
                                size: 'sm',
                            })}
                        >
                            Add {missingStandards.length} standard benchmarks
                        </button>
                    )}
                    {count > 0 && (
                        <button
                            type="button"
                            onClick={() => form.setData('benchmarks', {})}
                            className={buttonClass({
                                variant: 'ghost',
                                className: 'hover:text-bad',
                            })}
                        >
                            Clear all
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-8 flex flex-col gap-4 pb-24">
                {METRIC_GROUPS.map((group) => (
                    <Panel
                        key={group.title}
                        title={group.title}
                        bodyClassName="px-5 pb-2 pt-1 divide-y divide-line"
                    >
                        {(
                            Object.entries(group.metrics) as [
                                MetricKey,
                                MetricInfo,
                            ][]
                        ).map(([key, metric]) => (
                            <BenchmarkRow
                                key={key}
                                metricKey={key}
                                metric={metric}
                                perWeek={weekly(key)}
                                standard={standards[key]}
                                draft={drafts[key]}
                                unreadable={unreadable}
                                error={
                                    (form.errors as Record<string, string>)[
                                        `benchmarks.${key}`
                                    ] ??
                                    LEVELS.map(
                                        (level) =>
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string
                                                >
                                            )[`benchmarks.${key}.${level}`],
                                    ).find(Boolean)
                                }
                                onChange={(draft) => setDraft(key, draft)}
                                onStandard={() =>
                                    setDraft(key, toDraft(key, standards[key]!))
                                }
                            />
                        ))}
                    </Panel>
                ))}
            </div>

            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-page/85 backdrop-blur-md">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
                    <span className="text-xs text-ink-muted">
                        {form.recentlySuccessful
                            ? 'Saved.'
                            : `${count} metric${count === 1 ? '' : 's'} benchmarked${form.isDirty ? ' · unsaved changes' : ''}`}
                    </span>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={form.processing || !form.isDirty}
                        className={buttonClass({
                            variant: 'primary',
                            size: 'md',
                        })}
                    >
                        {form.processing ? 'Saving…' : 'Save benchmarks'}
                    </button>
                </div>
            </div>
        </SettingsLayout>
    );
}

function BenchmarkRow({
    metricKey,
    metric,
    perWeek,
    standard,
    draft,
    unreadable,
    error,
    onChange,
    onStandard,
}: {
    metricKey: MetricKey;
    metric: MetricInfo;
    perWeek: boolean;
    standard: Standard | undefined;
    draft: Draft | undefined;
    unreadable: Set<string>;
    error: string | undefined;
    onChange: (draft: Draft | undefined) => void;
    onStandard: () => void;
}) {
    const suffix = inputSuffix(metric.unit, perWeek);
    const sign = draft?.direction === 'higher' ? '≥' : '≤';

    return (
        <div className="flex flex-col gap-3 py-3.5 lg:flex-row lg:items-start lg:gap-6">
            <div className="min-w-0 lg:w-72 lg:shrink-0">
                <div className="text-sm text-ink">{metric.label}</div>
                <div className="text-[11px] text-ink-muted">{metric.hint}</div>
                {standard && (
                    <div className="mt-1 text-[11px] text-ink-muted">
                        <span className="font-medium text-ink-secondary">
                            {standard.source}
                        </span>{' '}
                        · {describeThresholds(standard, metric.unit, perWeek)}
                    </div>
                )}
            </div>

            {draft ? (
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        {metric.better === 'neutral' ? (
                            <Select
                                label={`${metric.label}: better when`}
                                value={draft.direction}
                                onChange={(direction) =>
                                    onChange({ ...draft, direction })
                                }
                                options={[
                                    {
                                        value: 'lower',
                                        label: 'Lower is better',
                                    },
                                    {
                                        value: 'higher',
                                        label: 'Higher is better',
                                    },
                                ]}
                            />
                        ) : (
                            <span className="w-28 text-xs text-ink-muted">
                                {draft.direction === 'higher'
                                    ? 'Higher is better'
                                    : 'Lower is better'}
                            </span>
                        )}
                        {LEVELS.map((level) => (
                            <label
                                key={level}
                                className={cn(
                                    'flex h-8 items-center gap-1.5 rounded-lg border bg-surface pr-2 pl-1 text-xs focus-within:border-accent focus-within:ring-2 focus-within:ring-accent-soft',
                                    unreadable.has(`${metricKey}.${level}`)
                                        ? 'border-bad'
                                        : 'border-line',
                                )}
                            >
                                <TierChip tier={level} />
                                <span className="text-ink-muted">{sign}</span>
                                <input
                                    value={draft[level]}
                                    onChange={(event) =>
                                        onChange({
                                            ...draft,
                                            [level]: event.target.value,
                                        })
                                    }
                                    inputMode={
                                        metric.unit === 'duration'
                                            ? 'text'
                                            : 'decimal'
                                    }
                                    placeholder={
                                        metric.unit === 'duration' ? '4h' : '0'
                                    }
                                    aria-label={`${metric.label}: ${TIER_LABELS[level]}`}
                                    className="tabular w-14 bg-transparent text-right text-ink outline-none"
                                />
                                {suffix && (
                                    <span className="text-ink-muted">
                                        {suffix}
                                    </span>
                                )}
                            </label>
                        ))}
                        <span className="flex items-center gap-1.5 text-xs text-ink-muted">
                            <TierChip tier="low" /> otherwise
                        </span>
                        <div className="ml-auto flex items-center gap-1">
                            {standard && (
                                <RowButton onClick={onStandard}>
                                    Reset to {standard.source}
                                </RowButton>
                            )}
                            <RowButton onClick={() => onChange(undefined)}>
                                Remove
                            </RowButton>
                        </div>
                    </div>
                    {error && <p className="text-xs text-bad">{error}</p>}
                    {[...unreadable].some((entry) =>
                        entry.startsWith(`${metricKey}.`),
                    ) && (
                        <p className="text-xs text-bad">
                            {metric.unit === 'duration'
                                ? 'Use durations like 30m, 4h or 2d.'
                                : 'Use a positive number.'}
                        </p>
                    )}
                </div>
            ) : (
                <div className="flex flex-1 items-center gap-1 lg:justify-end">
                    {standard && (
                        <RowButton onClick={onStandard}>
                            Use {standard.source}
                        </RowButton>
                    )}
                    <RowButton
                        onClick={() =>
                            onChange({
                                direction:
                                    metric.better === 'higher'
                                        ? 'higher'
                                        : 'lower',
                                elite: '',
                                good: '',
                                medium: '',
                            })
                        }
                    >
                        Set custom
                    </RowButton>
                </div>
            )}
        </div>
    );
}

function RowButton({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="inline-flex h-7 items-center rounded-md px-2 text-xs font-medium text-ink-secondary transition hover:bg-line/60 hover:text-ink"
        >
            {children}
        </button>
    );
}

function TierChip({ tier }: { tier: keyof typeof TIER_TONES }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-1.5 py-px text-[11px] font-medium',
                TIER_TONES[tier],
            )}
        >
            {TIER_LABELS[tier]}
        </span>
    );
}
