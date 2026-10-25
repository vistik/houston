import { cn } from '@/lib/utils';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md';

/* Mirrors the landing page's buttons: a solid primary, an outlined secondary and a bare ghost, plus a solid danger for destructive actions. */
const VARIANTS: Record<ButtonVariant, string> = {
    primary: 'bg-accent text-on-accent hover:bg-accent/90',
    secondary:
        'border border-line-strong bg-surface text-ink hover:border-ink-muted',
    ghost: 'text-ink-secondary hover:bg-line/60 hover:text-ink',
    danger: 'bg-bad text-on-accent hover:bg-bad/90',
};

const SIZES: Record<ButtonSize, string> = {
    sm: 'h-8 px-3 text-xs',
    md: 'h-9 px-4 text-sm',
};

export function buttonClass({
    variant = 'primary',
    size = 'sm',
    className,
}: {
    variant?: ButtonVariant;
    size?: ButtonSize;
    className?: string;
} = {}) {
    return cn(
        'inline-flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg font-semibold whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-50',
        VARIANTS[variant],
        SIZES[size],
        className,
    );
}
