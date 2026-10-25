import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type BadgeTone = 'neutral' | 'good' | 'bad' | 'warn' | 'accent';

const TONES: Record<BadgeTone, string> = {
    neutral: 'bg-line/70 text-ink-secondary',
    good: 'bg-good-soft text-good',
    bad: 'bg-bad-soft text-bad',
    warn: 'bg-warn-soft text-warn',
    accent: 'bg-accent-soft text-accent',
};

export function Badge({
    tone = 'neutral',
    children,
    className,
}: {
    tone?: BadgeTone;
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium whitespace-nowrap',
                TONES[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
