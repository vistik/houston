import { cn } from '@/lib/utils';

export function Avatar({
    src,
    login,
    size = 20,
    className,
}: {
    src: string | null | undefined;
    login: string | null;
    size?: number;
    className?: string;
}) {
    const fallback = (login ?? '?').slice(0, 1).toUpperCase();

    return src ? (
        <img
            src={`${src}${src.includes('?') ? '&' : '?'}s=${size * 2}`}
            alt=""
            width={size}
            height={size}
            loading="lazy"
            className={cn('shrink-0 rounded-full bg-line', className)}
        />
    ) : (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full bg-line text-[10px] font-semibold text-ink-secondary',
                className,
            )}
            style={{ width: size, height: size }}
        >
            {fallback}
        </span>
    );
}
