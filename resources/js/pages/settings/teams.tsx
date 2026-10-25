import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, TrashIcon } from '@/components/icons';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Panel } from '@/components/ui/panel';
import SettingsLayout from '@/layouts/settings-layout';
import { formatDate } from '@/lib/format';
import {
    destroy as destroyMembership,
    update as updateMembership,
} from '@/routes/settings/memberships';
import {
    destroy as destroyTeam,
    store as storeTeam,
    update as updateTeam,
} from '@/routes/settings/teams';
import { store as storeMembership } from '@/routes/settings/teams/memberships';
import { show } from '@/routes/teams';
import type { TeamInfo, TeamMembership } from '@/types';
import { buttonClass } from '@/components/ui/button';

const input =
    'h-8 rounded-lg border border-line bg-surface px-2.5 text-sm text-ink placeholder:text-ink-muted focus:border-line-strong focus:outline-none';
const primary = buttonClass({ variant: 'primary', size: 'sm' });
const secondary = buttonClass({ variant: 'secondary', size: 'sm' });

export default function Teams({
    teams,
    authors,
}: {
    teams: TeamInfo[];
    authors: string[];
}) {
    const form = useForm({ name: '' });

    return (
        <SettingsLayout>
            <Head title="Teams" />

            <p className="max-w-2xl text-sm text-ink-secondary">
                Group people by their GitHub username to report on their pull
                requests across every watched repository. Someone is on one team
                at a time: adding them to another team from a given day moves
                them, so their earlier work stays with the team they did it for.
            </p>

            <datalist id="team-authors">
                {authors.map((author) => (
                    <option key={author} value={author} />
                ))}
            </datalist>

            <form
                className="mt-8 flex flex-wrap items-start gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.submit(storeTeam(), {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <div>
                    <input
                        aria-label="Team name"
                        placeholder="New team name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        className={`${input} w-64`}
                    />
                    {form.errors.name && (
                        <p className="mt-1 text-[11px] text-bad">
                            {form.errors.name}
                        </p>
                    )}
                </div>
                <button
                    type="submit"
                    disabled={form.processing || form.data.name.trim() === ''}
                    className={primary}
                >
                    <PlusIcon width={13} height={13} />
                    Create team
                </button>
            </form>

            <div className="mt-6 flex flex-col gap-4">
                {teams.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-line-strong px-6 py-10 text-center text-sm text-ink-muted">
                        No teams yet.
                    </p>
                ) : (
                    teams.map((team) => <TeamPanel key={team.id} team={team} />)
                )}
            </div>
        </SettingsLayout>
    );
}

function TeamPanel({ team }: { team: TeamInfo }) {
    const [renaming, setRenaming] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const rename = useForm({ name: team.name });

    const title = renaming ? (
        <form
            className="flex items-start gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                rename.submit(updateTeam(team.slug), {
                    preserveScroll: true,
                    onSuccess: () => setRenaming(false),
                });
            }}
        >
            <div>
                <input
                    aria-label="Team name"
                    value={rename.data.name}
                    onChange={(event) =>
                        rename.setData('name', event.target.value)
                    }
                    className={`${input} w-56`}
                    autoFocus
                />
                {rename.errors.name && (
                    <p className="mt-1 text-[11px] font-normal text-bad">
                        {rename.errors.name}
                    </p>
                )}
            </div>
            <button
                type="submit"
                disabled={rename.processing}
                className={primary}
            >
                Save
            </button>
            <button
                type="button"
                onClick={() => {
                    rename.reset();
                    setRenaming(false);
                }}
                className={secondary}
            >
                Cancel
            </button>
        </form>
    ) : (
        team.name
    );

    return (
        <Panel
            title={title}
            description={
                renaming
                    ? undefined
                    : `${team.memberships.filter((membership) => membership.current).length} current members`
            }
            actions={
                !renaming && (
                    <div className="flex items-center gap-1.5">
                        <Link href={show(team.slug)} className={secondary}>
                            View report
                        </Link>
                        <button
                            type="button"
                            onClick={() => setRenaming(true)}
                            className={secondary}
                        >
                            Rename
                        </button>
                        <button
                            type="button"
                            aria-label={`Delete ${team.name}`}
                            onClick={() => setConfirmingDelete(true)}
                            className="inline-flex size-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-bad-soft hover:text-bad"
                        >
                            <TrashIcon width={14} height={14} />
                        </button>
                        <ConfirmDialog
                            open={confirmingDelete}
                            onClose={() => setConfirmingDelete(false)}
                            onConfirm={(visit) =>
                                router.delete(destroyTeam.url(team.slug), {
                                    preserveScroll: true,
                                    ...visit,
                                })
                            }
                            title={`Delete ${team.name}?`}
                            description="Its memberships are removed too; pull requests are kept."
                            confirmLabel="Delete team"
                            processingLabel="Deleting…"
                            destructive
                        />
                    </div>
                )
            }
            className="overflow-hidden"
            bodyClassName="mt-3 p-0"
        >
            {team.memberships.length > 0 && (
                <div className="overflow-x-auto border-t border-line">
                    <table className="w-full text-sm">
                        <thead className="text-left text-[11px] font-medium text-ink-muted">
                            <tr className="border-b border-line">
                                <th className="px-5 py-2 font-medium">
                                    Member
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Joined
                                </th>
                                <th className="px-3 py-2 font-medium">Left</th>
                                <th className="px-5 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {team.memberships.map((membership) => (
                                <MembershipRow
                                    // Saved changes, including a membership another team ended, start the row afresh.
                                    key={`${membership.id}:${membership.joinedOn}:${membership.leftOn}`}
                                    membership={membership}
                                    teamName={team.name}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            <AddMember team={team} />
        </Panel>
    );
}

function MembershipRow({
    membership,
    teamName,
}: {
    membership: TeamMembership;
    teamName: string;
}) {
    const form = useForm({
        joined_on: membership.joinedOn ?? '',
        left_on: membership.leftOn ?? '',
    });
    const upcoming =
        !membership.current &&
        membership.joinedOn !== null &&
        membership.joinedOn > new Date().toISOString().slice(0, 10);
    const error = form.errors.joined_on ?? form.errors.left_on;
    const [confirmingRemove, setConfirmingRemove] = useState(false);

    return (
        <tr className="border-b border-line align-top last:border-0">
            <td className="px-5 py-2.5">
                <div className="flex items-center gap-2">
                    <Avatar
                        src={membership.avatarUrl}
                        login={membership.login}
                    />
                    <span className="font-medium">{membership.login}</span>
                    {membership.current ? null : upcoming ? (
                        <Badge tone="accent">Upcoming</Badge>
                    ) : (
                        <Badge tone="neutral">Former</Badge>
                    )}
                </div>
                {error && <p className="mt-1 text-[11px] text-bad">{error}</p>}
            </td>
            <td className="px-3 py-2">
                <input
                    type="date"
                    aria-label={`Day ${membership.login} joined ${teamName}`}
                    title="Leave empty when they were always on the team"
                    value={form.data.joined_on}
                    onChange={(event) =>
                        form.setData('joined_on', event.target.value)
                    }
                    className={input}
                />
            </td>
            <td className="px-3 py-2">
                <input
                    type="date"
                    aria-label={`Day ${membership.login} left ${teamName}`}
                    title="The first day they were no longer on the team. Leave empty while they still are."
                    value={form.data.left_on}
                    onChange={(event) =>
                        form.setData('left_on', event.target.value)
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
                                form.submit(updateMembership(membership.id), {
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
                        aria-label={`Remove ${membership.login} from ${teamName}`}
                        title={
                            membership.joinedOn
                                ? `Remove this membership since ${formatDate(membership.joinedOn)}. To record that they left, set the day they left instead.`
                                : 'Remove this membership. To record that they left, set the day they left instead.'
                        }
                        onClick={() => setConfirmingRemove(true)}
                        className="inline-flex size-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-bad-soft hover:text-bad"
                    >
                        <TrashIcon width={14} height={14} />
                    </button>
                    <ConfirmDialog
                        open={confirmingRemove}
                        onClose={() => setConfirmingRemove(false)}
                        onConfirm={(visit) =>
                            router.delete(
                                destroyMembership.url(membership.id),
                                { preserveScroll: true, ...visit },
                            )
                        }
                        title={`Remove ${membership.login} from ${teamName}?`}
                        description="Their pull requests will no longer count for the team at all. To keep their earlier work with the team, set the day they left instead."
                        confirmLabel="Remove member"
                        processingLabel="Removing…"
                        destructive
                    />
                </div>
            </td>
        </tr>
    );
}

function AddMember({ team }: { team: TeamInfo }) {
    const form = useForm({ login: '', joined_on: '' });
    const error = form.errors.login ?? form.errors.joined_on;

    return (
        <form
            className="flex flex-wrap items-center gap-2 border-t border-line bg-page/40 px-5 py-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.submit(storeMembership(team.slug), {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <input
                aria-label={`GitHub username to add to ${team.name}`}
                placeholder="GitHub username"
                list="team-authors"
                value={form.data.login}
                onChange={(event) => form.setData('login', event.target.value)}
                className={`${input} w-52`}
            />
            <label className="flex items-center gap-2 text-xs text-ink-secondary">
                from
                <input
                    type="date"
                    aria-label="Day they joined"
                    value={form.data.joined_on}
                    onChange={(event) =>
                        form.setData('joined_on', event.target.value)
                    }
                    className={input}
                />
            </label>
            <button
                type="submit"
                disabled={form.processing || form.data.login.trim() === ''}
                className={primary}
            >
                <PlusIcon width={13} height={13} />
                Add member
            </button>
            <span className="text-[11px] text-ink-muted">
                Without a day, they count as always having been on the team.
            </span>
            {error && <p className="w-full text-[11px] text-bad">{error}</p>}
        </form>
    );
}
