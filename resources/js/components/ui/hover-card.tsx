import {
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

const GAP = 6;
const MARGIN = 12;

/**
 * How keyboard users reach a card: by focusing the trigger itself, or the link or button the trigger
 * sits in (for one card per control, like a pickable row).
 */
export type HoverCardFocus = 'self' | 'control';

/**
 * Shows a card the moment its trigger is hovered, or focused from the keyboard when `focus` is set.
 * Escape closes it. The card renders in a portal so cards and panels that clip their overflow don't
 * cut it off, and flips above when there is no room below.
 */
export function HoverCard({
    card,
    children,
    className,
    style,
    panelClassName = 'w-72 rounded-xl p-3',
    focus,
}: {
    card: ReactNode;
    children: ReactNode;
    className?: string;
    style?: CSSProperties;
    panelClassName?: string;
    focus?: HoverCardFocus;
}) {
    const id = useId();
    const trigger = useRef<HTMLSpanElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const control = useRef<HTMLElement | null>(null);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState<{
        top: number;
        left: number;
    } | null>(null);

    const hide = () => {
        setOpen(false);
        setPosition(null);
    };

    // Only keyboard focus opens the card; a click that focuses the control shouldn't pin it open.
    const showOnKeyboardFocus = (element: Element) => {
        if (element.matches(':focus-visible')) {
            setOpen(true);
        }
    };

    useEffect(() => {
        const element =
            focus === 'control'
                ? trigger.current?.parentElement?.closest<HTMLElement>(
                      'a[href], button',
                  )
                : null;

        if (!element) {
            return;
        }

        control.current = element;
        const show = () => showOnKeyboardFocus(element);

        element.addEventListener('focus', show);
        element.addEventListener('blur', hide);

        return () => {
            element.removeEventListener('focus', show);
            element.removeEventListener('blur', hide);
            control.current = null;
        };
    }, [focus]);

    useEffect(() => {
        const element = control.current;

        if (!open || !element || element.hasAttribute('aria-describedby')) {
            return;
        }

        element.setAttribute('aria-describedby', id);

        return () => element.removeAttribute('aria-describedby');
    }, [open, id]);

    useLayoutEffect(() => {
        if (!open || !trigger.current || !panel.current) {
            return;
        }

        const target = trigger.current.getBoundingClientRect();
        const { width, height } = panel.current.getBoundingClientRect();
        const fitsBelow =
            target.bottom + GAP + height <= window.innerHeight - MARGIN;

        setPosition({
            top: fitsBelow
                ? target.bottom + GAP
                : Math.max(MARGIN, target.top - GAP - height),
            left: Math.min(
                Math.max(MARGIN, target.left + target.width / 2 - width / 2),
                window.innerWidth - MARGIN - width,
            ),
        });
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                hide();
            }
        };

        window.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            window.removeEventListener('scroll', hide, true);
            window.removeEventListener('resize', hide);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    return (
        <span
            ref={trigger}
            className={cn('inline-flex', className)}
            style={style}
            aria-describedby={open ? id : undefined}
            tabIndex={focus === 'self' ? 0 : undefined}
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={hide}
            onFocus={
                focus === 'self'
                    ? (event) => showOnKeyboardFocus(event.currentTarget)
                    : undefined
            }
            onBlur={focus === 'self' ? hide : undefined}
        >
            {children}
            {open &&
                createPortal(
                    <div
                        ref={panel}
                        id={id}
                        role="tooltip"
                        className={cn(
                            'pointer-events-none fixed z-50 border border-line bg-surface-raised text-left text-xs font-normal tracking-normal whitespace-normal text-ink-secondary normal-case shadow-lg shadow-black/10',
                            panelClassName,
                        )}
                        style={
                            position ?? {
                                top: 0,
                                left: 0,
                                visibility: 'hidden',
                            }
                        }
                    >
                        {card}
                    </div>,
                    document.body,
                )}
        </span>
    );
}

/**
 * A short hover label, shown instantly. Use HoverCard for anything with structure.
 */
export function Tooltip({
    content,
    children,
    className,
    style,
}: {
    content: ReactNode;
    children: ReactNode;
    className?: string;
    style?: CSSProperties;
}) {
    if (!content) {
        return children;
    }

    return (
        <HoverCard
            card={content}
            className={className}
            style={style}
            panelClassName="max-w-64 rounded-lg px-2.5 py-1.5 text-ink"
        >
            {children}
        </HoverCard>
    );
}
