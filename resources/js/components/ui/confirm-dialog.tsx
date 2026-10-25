import { useState } from 'react';
import type { ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { Dialog, DialogActions, INITIAL_FOCUS } from '@/components/ui/dialog';

/**
 * Callbacks to spread into the options of the Inertia visit a confirm dialog runs, so it shows a
 * loading state while the request is in flight and closes once it succeeds.
 */
export type ConfirmVisit = {
    onStart: () => void;
    onSuccess: () => void;
    onFinish: () => void;
};

/**
 * Asks to confirm an action before running it, in place of the browser's `confirm()`.
 *
 * `onConfirm` receives callbacks to spread into the Inertia visit it starts:
 *
 *     onConfirm={(visit) => router.delete(url, { preserveScroll: true, ...visit })}
 *
 * The dialog then stays open with a loading confirm button until the request finishes, and
 * closes when it succeeds; a failed request leaves it open to try again or cancel. A caller
 * that runs its own request, like a `useForm` submit, can pass `processing` and close it itself.
 */
export function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    title,
    description,
    children,
    confirmLabel = 'Confirm',
    processingLabel,
    cancelLabel = 'Cancel',
    destructive = false,
    processing,
}: {
    open: boolean;
    onClose: () => void;
    onConfirm: (visit: ConfirmVisit) => void;
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    confirmLabel?: string;
    processingLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    processing?: boolean;
}) {
    const [visiting, setVisiting] = useState(false);
    const busy = processing ?? visiting;

    const confirm = () => {
        if (busy) {
            return;
        }

        onConfirm({
            onStart: () => setVisiting(true),
            onSuccess: onClose,
            onFinish: () => setVisiting(false),
        });
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={title}
            description={description}
            dismissible={!busy}
        >
            {children}
            <DialogActions>
                <button
                    type="button"
                    onClick={onClose}
                    disabled={busy}
                    // Destructive actions start on the safe choice, so a stray Enter doesn't run them.
                    {...(destructive ? INITIAL_FOCUS : {})}
                    className={buttonClass({ variant: 'secondary', size: 'md' })}
                >
                    {cancelLabel}
                </button>
                <button
                    type="button"
                    onClick={confirm}
                    // Stays focusable while busy, so focus doesn't fall out of the dialog.
                    aria-disabled={busy}
                    aria-busy={busy}
                    {...(destructive ? {} : INITIAL_FOCUS)}
                    className={buttonClass({
                        variant: destructive ? 'danger' : 'primary',
                        size: 'md',
                        className:
                            'aria-disabled:cursor-progress aria-disabled:opacity-70',
                    })}
                >
                    {busy && (
                        <span
                            aria-hidden
                            className="size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none"
                        />
                    )}
                    {busy ? (processingLabel ?? confirmLabel) : confirmLabel}
                </button>
            </DialogActions>
        </Dialog>
    );
}
