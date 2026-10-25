import { Form, usePage } from '@inertiajs/react';
import { useId, useState } from 'react';
import { PlusIcon } from '@/components/icons';
import { RepositoryCombobox } from '@/components/repository-combobox';
import { buttonClass } from '@/components/ui/button';
import { Dialog, DialogActions } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { store } from '@/routes/repositories';

/**
 * A button that opens a modal to start watching a repository. Watching one closes the modal and
 * opens the repository's page. The demo watches a fixed set, so it has no button.
 */
export function WatchRepositoryButton({ className }: { className?: string }) {
    const { demo } = usePage().props;
    const [open, setOpen] = useState(false);
    const id = useId();

    if (demo) {
        return null;
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    buttonClass({ variant: 'primary', size: 'sm' }),
                    className,
                )}
            >
                <PlusIcon width={13} height={13} />
                Watch repository
            </button>

            <Dialog
                open={open}
                onClose={() => setOpen(false)}
                title="Watch a repository"
                description="Houston pulls its pull requests, reviews and CI runs from GitHub and keeps them as a local cache."
                // The suggestions list hangs below the input, past the dialog's edge.
                className="overflow-visible"
            >
                <Form
                    action={store()}
                    resetOnSuccess
                    // The page it opens can be another repository's, which keeps this button and its state.
                    onSuccess={() => setOpen(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-1.5">
                                <label
                                    htmlFor={`${id}-repository`}
                                    className="text-xs font-medium text-ink-secondary"
                                >
                                    Repository
                                </label>
                                <RepositoryCombobox
                                    initialFocus
                                    id={`${id}-repository`}
                                    name="repository"
                                    placeholder="owner/name or a GitHub URL"
                                />
                                {errors.repository && (
                                    <p className="text-sm text-bad">
                                        {errors.repository}
                                    </p>
                                )}
                            </div>

                            <DialogActions>
                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className={buttonClass({
                                        variant: 'secondary',
                                        size: 'md',
                                    })}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className={buttonClass({
                                        variant: 'primary',
                                        size: 'md',
                                    })}
                                >
                                    {processing ? 'Checking…' : 'Watch'}
                                </button>
                            </DialogActions>
                        </>
                    )}
                </Form>
            </Dialog>
        </>
    );
}
