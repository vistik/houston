import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, TrashIcon } from '@/components/icons';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Panel } from '@/components/ui/panel';
import SettingsLayout from '@/layouts/settings-layout';
import { toDateInput } from '@/lib/periods';
import { destroy, store, update } from '@/routes/settings/events';
import type { TimelineEvent } from '@/types';
import { buttonClass } from '@/components/ui/button';

const input =
    'h-8 rounded-lg border border-line bg-surface px-2.5 text-sm text-ink placeholder:text-ink-muted focus:border-line-strong focus:outline-none';
const primary = buttonClass({ variant: 'primary', size: 'sm' });

export default function Events({ events }: { events: TimelineEvent[] }) {
    const today = toDateInput(new Date());
    const form = useForm({ title: '', happened_on: today });
    const error = form.errors.title ?? form.errors.happened_on;

    return (
        <SettingsLayout>
            <Head title="Events" />

            <p className="max-w-2xl text-sm text-ink-secondary">
                Mark changes to how people work, like introducing AI reviews or
                a Slack bot that nudges reviewers. Each event shows as a line on
                every timeline; click it to compare the time since with the
                equally long stretch before.
            </p>

            <form
                className="mt-8 flex flex-wrap items-start gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.submit(store(), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <div>
                    <input
                        aria-label="What happened"
                        placeholder="Introduced AI reviews"
                        value={form.data.title}
                        maxLength={120}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        className={`${input} w-80`}
                    />
                    {error && (
                        <p className="mt-1 text-[11px] text-bad">{error}</p>
                    )}
                </div>
                <input
                    type="date"
                    aria-label="Day it happened"
                    value={form.data.happened_on}
                    max={today}
                    onChange={(event) =>
                        form.setData('happened_on', event.target.value)
                    }
                    className={input}
                />
                <button
                    type="submit"
                    disabled={
                        form.processing ||
                        form.data.title.trim() === '' ||
                        form.data.happened_on === ''
                    }
                    className={primary}
                >
                    <PlusIcon width={13} height={13} />
                    Add event
                </button>
            </form>

            <div className="mt-6">
                {events.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-line-strong px-6 py-10 text-center text-sm text-ink-muted">
                        No events yet.
                    </p>
                ) : (
                    <Panel className="overflow-hidden" bodyClassName="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-[11px] font-medium text-ink-muted">
                                <tr className="border-b border-line">
                                    <th className="px-5 py-2 font-medium">
                                        Event
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Day
                                    </th>
                                    <th className="px-5 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {events.map((event) => (
                                    <EventRow
                                        // Saved changes start the row afresh.
                                        key={`${event.id}:${event.title}:${event.date}`}
                                        event={event}
                                        today={today}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </Panel>
                )}
            </div>
        </SettingsLayout>
    );
}

function EventRow({ event, today }: { event: TimelineEvent; today: string }) {
    const form = useForm({ title: event.title, happened_on: event.date });
    const error = form.errors.title ?? form.errors.happened_on;
    const [confirmingRemove, setConfirmingRemove] = useState(false);

    return (
        <tr className="border-b border-line align-top last:border-0">
            <td className="px-5 py-2">
                <input
                    aria-label="What happened"
                    value={form.data.title}
                    maxLength={120}
                    onChange={(changed) =>
                        form.setData('title', changed.target.value)
                    }
                    className={`${input} w-full min-w-48`}
                />
                {error && <p className="mt-1 text-[11px] text-bad">{error}</p>}
            </td>
            <td className="px-3 py-2">
                <input
                    type="date"
                    aria-label={`Day ${event.title} happened`}
                    value={form.data.happened_on}
                    max={today}
                    onChange={(changed) =>
                        form.setData('happened_on', changed.target.value)
                    }
                    className={input}
                />
            </td>
            <td className="px-5 py-2">
                <div className="flex items-center justify-end gap-1.5">
                    {form.isDirty && (
                        <button
                            type="button"
                            disabled={form.processing}
                            onClick={() =>
                                form.submit(update(event.id), {
                                    preserveScroll: true,
                                })
                            }
                            className={primary}
                        >
                            Save
                        </button>
                    )}
                    <button
                        type="button"
                        aria-label={`Remove ${event.title}`}
                        onClick={() => setConfirmingRemove(true)}
                        className="inline-flex size-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-bad-soft hover:text-bad"
                    >
                        <TrashIcon width={14} height={14} />
                    </button>
                    <ConfirmDialog
                        open={confirmingRemove}
                        onClose={() => setConfirmingRemove(false)}
                        onConfirm={(visit) =>
                            router.delete(destroy.url(event.id), {
                                preserveScroll: true,
                                ...visit,
                            })
                        }
                        title={`Remove ${event.title}?`}
                        description="It will no longer show on the timelines."
                        confirmLabel="Remove event"
                        processingLabel="Removing…"
                        destructive
                    />
                </div>
            </td>
        </tr>
    );
}
