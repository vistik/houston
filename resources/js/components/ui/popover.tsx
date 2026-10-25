import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

const GAP = 6;
const MARGIN = 12;

/**
 * Marks a floating layer, like a select's list, whose clicks belong to the popover that opened it.
 */
export const LAYER = { 'data-floating-layer': '' };

/**
 * A panel that opens from its trigger and closes on Escape or a click elsewhere. It renders
 * in a portal aligned to the trigger's right edge, and flips above when there is no room below.
 */
export function Popover({
    trigger,
    children,
    label,
    role = 'dialog',
    className,
}: {
    trigger: (props: {
        open: boolean;
        toggle: () => void;
        ref: React.RefObject<HTMLButtonElement | null>;
        'aria-expanded': boolean;
        'aria-haspopup': 'dialog' | 'menu';
        'aria-controls': string | undefined;
    }) => ReactNode;
    children: (close: () => void) => ReactNode;
    label: string;
    role?: 'dialog' | 'menu';
    className?: string;
}) {
    const id = useId();
    const anchor = useRef<HTMLButtonElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState<{
        top: number;
        left: number;
    } | null>(null);

    const close = () => {
        setOpen(false);
        setPosition(null);
    };

    useLayoutEffect(() => {
        if (!open || !anchor.current || !panel.current) {
            return;
        }

        const target = anchor.current.getBoundingClientRect();
        const { width, height } = panel.current.getBoundingClientRect();
        const fitsBelow =
            target.bottom + GAP + height <= window.innerHeight - MARGIN;

        setPosition({
            top: fitsBelow
                ? target.bottom + GAP
                : Math.max(MARGIN, target.top - GAP - height),
            left: Math.min(
                Math.max(MARGIN, target.right - width),
                window.innerWidth - MARGIN - width,
            ),
        });
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const outside = (event: Event) => {
            const node = event.target as Element;

            if (
                !anchor.current?.contains(node) &&
                !panel.current?.contains(node) &&
                !node.closest?.('[data-floating-layer]')
            ) {
                close();
            }
        };

        const escape = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !event.defaultPrevented) {
                close();
                anchor.current?.focus();
            }
        };

        window.addEventListener('mousedown', outside);
        window.addEventListener('scroll', outside, true);
        window.addEventListener('resize', close);
        window.addEventListener('keydown', escape);

        return () => {
            window.removeEventListener('mousedown', outside);
            window.removeEventListener('scroll', outside, true);
            window.removeEventListener('resize', close);
            window.removeEventListener('keydown', escape);
        };
    }, [open]);

    return (
        <>
            {trigger({
                open,
                toggle: () => (open ? close() : setOpen(true)),
                ref: anchor,
                'aria-expanded': open,
                'aria-haspopup': role,
                'aria-controls': open ? id : undefined,
            })}
            {open &&
                createPortal(
                    <div
                        ref={panel}
                        id={id}
                        role={role}
                        aria-label={label}
                        className={cn(
                            'fixed z-40 rounded-xl border border-line bg-surface-raised text-xs shadow-lg shadow-black/10',
                            className,
                        )}
                        style={
                            position ?? {
                                top: 0,
                                left: 0,
                                visibility: 'hidden',
                            }
                        }
                    >
                        {children(close)}
                    </div>,
                    document.body,
                )}
        </>
    );
}
