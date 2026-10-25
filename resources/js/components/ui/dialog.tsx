import { useEffect, useId, useLayoutEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { XIcon } from '@/components/icons';
import { cn } from '@/lib/utils';

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Marks the element a dialog focuses when it opens, like `autoFocus` (which React only applies
 * on mount, before the dialog is shown). Without one, the browser focuses the first focusable.
 */
export const INITIAL_FOCUS = { 'data-autofocus': '' };

/**
 * A modal dialog in the app's style, built on the native `<dialog>` element so the browser keeps
 * it in the top layer, makes the page behind it inert and announces it as modal. On top of that
 * it keeps Tab inside it, closes on Escape, a backdrop click or its close button, locks the page's
 * scroll and gives focus back to whatever had it before it opened.
 *
 * It is controlled: it shows while `open` is true and asks to close through `onClose`. Set
 * `dismissible` to false while a request runs to keep it open until that finishes.
 */
export function Dialog({
    open,
    onClose,
    title,
    description,
    children,
    dismissible = true,
    className,
}: {
    open: boolean;
    onClose: () => void;
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    dismissible?: boolean;
    className?: string;
}) {
    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <DialogPanel
            onClose={onClose}
            title={title}
            description={description}
            dismissible={dismissible}
            className={className}
        >
            {children}
        </DialogPanel>,
        document.body,
    );
}

function DialogPanel({
    onClose,
    title,
    description,
    children,
    dismissible,
    className,
}: {
    onClose: () => void;
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    dismissible: boolean;
    className?: string;
}) {
    const id = useId();
    const dialog = useRef<HTMLDialogElement>(null);
    const pressedOnBackdrop = useRef(false);
    const latest = useRef({ onClose, dismissible });
    const unmounting = useRef(false);

    useLayoutEffect(() => {
        latest.current = { onClose, dismissible };
    });

    useLayoutEffect(() => {
        const element = dialog.current;

        if (!element) {
            return;
        }

        const opener =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        const root = document.documentElement;
        const previousOverflow = root.style.overflow;
        const previousPaddingRight = root.style.paddingRight;
        const scrollbarWidth = window.innerWidth - root.clientWidth;

        root.style.overflow = 'hidden';

        if (scrollbarWidth > 0) {
            root.style.paddingRight = `${scrollbarWidth}px`;
        }

        element.showModal();
        element.querySelector<HTMLElement>('[data-autofocus]')?.focus();

        return () => {
            unmounting.current = true;
            element.close();
            root.style.overflow = previousOverflow;
            root.style.paddingRight = previousPaddingRight;

            if (opener?.isConnected) {
                opener.focus();
            }
        };
    }, []);

    useEffect(() => {
        const element = dialog.current;

        if (!element) {
            return;
        }

        // Escape fires `cancel`; the parent decides whether the dialog closes.
        const cancel = (event: Event) => {
            event.preventDefault();

            if (latest.current.dismissible) {
                latest.current.onClose();
            }
        };

        // Browsers may close a dialog themselves, like on a repeated Escape; keep the parent in step.
        const close = () => {
            if (!unmounting.current) {
                latest.current.onClose();
            }
        };

        element.addEventListener('cancel', cancel);
        element.addEventListener('close', close);

        return () => {
            element.removeEventListener('cancel', cancel);
            element.removeEventListener('close', close);
        };
    }, []);

    const trapTab = (event: React.KeyboardEvent<HTMLDialogElement>) => {
        if (event.key !== 'Tab' || !dialog.current) {
            return;
        }

        const focusable = Array.from(
            dialog.current.querySelectorAll<HTMLElement>(FOCUSABLE),
        ).filter((element) => element.getClientRects().length > 0);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            event.preventDefault();

            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return (
        <dialog
            ref={dialog}
            aria-modal="true"
            aria-labelledby={`${id}-title`}
            aria-describedby={description ? `${id}-description` : undefined}
            onKeyDown={trapTab}
            // A press on the backdrop lands on the dialog itself; require the press to start there too, so a drag out of a field doesn't close it.
            onMouseDown={(event) => {
                pressedOnBackdrop.current = event.target === dialog.current;
            }}
            onClick={(event) => {
                if (
                    pressedOnBackdrop.current &&
                    event.target === dialog.current &&
                    dismissible
                ) {
                    onClose();
                }
            }}
            className={cn(
                'm-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-line bg-surface p-0 text-ink shadow-xl shadow-black/20',
                'animate-dialog-in backdrop:animate-backdrop-in backdrop:bg-black/40 backdrop:backdrop-blur-sm motion-reduce:animate-none motion-reduce:backdrop:animate-none',
                className,
            )}
        >
            <div className="flex flex-col gap-4 p-5">
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h2
                            id={`${id}-title`}
                            className="font-semibold tracking-tight"
                        >
                            {title}
                        </h2>
                        {description && (
                            <p
                                id={`${id}-description`}
                                className="mt-1 text-sm text-ink-secondary"
                            >
                                {description}
                            </p>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={!dismissible}
                        aria-label="Close"
                        className="inline-flex size-7 shrink-0 cursor-pointer items-center justify-center rounded-lg text-ink-muted transition hover:bg-line hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <XIcon width={14} height={14} />
                    </button>
                </div>
                {children}
            </div>
        </dialog>
    );
}

/**
 * The row of buttons at the bottom of a dialog, with its main action last.
 */
export function DialogActions({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {children}
        </div>
    );
}
