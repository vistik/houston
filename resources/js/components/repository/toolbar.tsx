import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    CheckIcon,
    ChevronDownIcon,
    EllipsisIcon,
    MonitorIcon,
    RefreshIcon,
} from '@/components/icons';
import { PeriodPicker } from '@/components/period-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover } from '@/components/ui/popover';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { fitsPeriod, GRANULARITY_OPTIONS, periodLabel } from '@/lib/periods';
import type { Granularity, PeriodFilters } from '@/lib/periods';
import { cn } from '@/lib/utils';
import { sync } from '@/routes/repositories';
import type { RepositoryInfo } from '@/types';
import { buttonClass } from '@/components/ui/button';

type Options = PeriodFilters & {
    granularity: Granularity | null;
    complete: boolean;
    /** Left out for reports that only count people. */
    bots?: boolean;
};

const CADENCE: Record<Granularity, string> = {
    day: 'Daily',
    week: 'Weekly',
    month: 'Monthly',
};

const trigger = buttonClass({
    variant: 'secondary',
    className: 'px-0 aria-expanded:border-ink-muted',
});

/**
 * The period, chart grouping and what the report counts, behind one trigger that sums them up.
 */
export function ReportOptions({
    filters,
    granularity,
    days,
    onChange,
}: {
    filters: Options;
    granularity: Granularity;
    days: number;
    onChange: (changes: Partial<Options>) => void;
}) {
    const period = periodLabel(filters);

    return (
        <Popover
            label="Report options"
            className="flex w-80 flex-col gap-4 p-4"
            trigger={({ open, toggle, ref, ...aria }) => (
                <button
                    ref={ref}
                    type="button"
                    onClick={toggle}
                    className={cn(trigger, 'gap-1.5 pr-2 pl-3')}
                    {...aria}
                >
                    <span className="first-letter:uppercase">{period}</span>
                    <span className="text-line-strong">·</span>
                    {CADENCE[granularity]}
                    <ChevronDownIcon
                        width={13}
                        height={13}
                        className={cn(
                            'text-ink-muted transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            )}
        >
            {() => (
                <>
                    <Field label="Period">
                        <PeriodPicker
                            inline
                            value={filters}
                            onChange={(next) => onChange(next)}
                        />
                    </Field>
                    <Field label="Chart each">
                        <SegmentedControl
                            label="Chart each"
                            stretch
                            options={GRANULARITY_OPTIONS.map((option) => {
                                const fits = fitsPeriod(option.days, days);

                                return {
                                    value: option.value,
                                    label: option.label,
                                    disabled: !fits,
                                    title: fits
                                        ? `Chart trends per ${option.value}`
                                        : `Pick a period of at least ${2 * option.days} days to chart per ${option.value}`,
                                };
                            })}
                            value={granularity}
                            onChange={(next) => onChange({ granularity: next })}
                        />
                    </Field>
                    <div className="flex flex-col gap-3 border-t border-line pt-4">
                        {filters.bots !== undefined && (
                            <Checkbox
                                checked={filters.bots}
                                onChange={(bots) => onChange({ bots })}
                                label="Include bot PRs"
                                description="Count pull requests opened by bots like Dependabot."
                            />
                        )}
                        <Checkbox
                            checked={filters.complete}
                            onChange={(complete) => onChange({ complete })}
                            label={`Exclude current ${granularity}`}
                            description="Its incomplete data won't read as a drop in the charts."
                        />
                    </div>
                </>
            )}
        </Popover>
    );
}

/**
 * Refreshing from GitHub and other ways to look at the repository.
 */
export function RepositoryActions<View extends string>({
    repository,
    wallUrl,
    view,
    views,
    onViewChange,
}: {
    repository: RepositoryInfo;
    wallUrl: string;
    /** The view the page shows, and the ones it can switch to. */
    view: View;
    views: { value: View; label: string; title: string }[];
    onViewChange: (view: View) => void;
}) {
    const { demo } = usePage().props;
    const item =
        'flex w-full items-center gap-2 rounded-lg py-1.5 pr-2.5 pl-2 text-left whitespace-nowrap text-ink-secondary hover:bg-line/60 hover:text-ink disabled:pointer-events-none disabled:opacity-50';

    return (
        <Popover
            label="Repository actions"
            role="menu"
            className="min-w-44 p-1"
            trigger={({ toggle, ref, ...aria }) => (
                <button
                    ref={ref}
                    type="button"
                    onClick={toggle}
                    aria-label="Repository actions"
                    className={cn(trigger, 'w-8 justify-center')}
                    {...aria}
                >
                    <EllipsisIcon width={16} height={16} />
                </button>
            )}
        >
            {(close) => (
                <>
                    {!demo && (
                        <button
                            type="button"
                            role="menuitem"
                            disabled={repository.sync.inProgress}
                            onClick={() => {
                                close();
                                router.post(
                                    sync(repository.id).url,
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                            className={item}
                        >
                            <RefreshIcon width={14} height={14} />
                            Refresh from GitHub
                        </button>
                    )}
                    <a role="menuitem" href={wallUrl} className={item}>
                        <MonitorIcon width={14} height={14} />
                        Wall display
                    </a>
                    <div
                        role="group"
                        aria-label="View"
                        className="mt-1 border-t border-line pt-1"
                    >
                        <div className="px-2 pt-1 pb-0.5 text-[11px] font-medium text-ink-muted uppercase">
                            View
                        </div>
                        {views.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="menuitemradio"
                                aria-checked={option.value === view}
                                title={option.title}
                                onClick={() => {
                                    close();

                                    if (option.value !== view) {
                                        onViewChange(option.value);
                                    }
                                }}
                                className={item}
                            >
                                <CheckIcon
                                    width={14}
                                    height={14}
                                    className={
                                        option.value === view
                                            ? 'text-accent'
                                            : 'invisible'
                                    }
                                />
                                {option.label}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </Popover>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-xs font-medium text-ink-muted">{label}</span>
            {children}
        </div>
    );
}
