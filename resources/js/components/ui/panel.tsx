import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Panel({
    title,
    description,
    actions,
    children,
    className,
    bodyClassName,
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section
            className={cn(
                'min-w-0 rounded-xl border border-line bg-surface shadow-[0_1px_2px_rgba(0,0,0,0.03)]',
                className,
            )}
        >
            {(title || actions) && (
                <header className="flex items-start justify-between gap-4 px-5 pt-4">
                    <div>
                        {title && (
                            <h2 className="text-sm font-semibold text-ink">
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p className="mt-0.5 text-xs text-ink-muted">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions}
                </header>
            )}
            <div className={cn('p-5', bodyClassName)}>{children}</div>
        </section>
    );
}

export function SectionHeading({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <div className="mt-10 mb-4 flex items-baseline gap-3">
            <h2 className="text-base font-semibold tracking-tight">{title}</h2>
            {description && (
                <p className="text-sm text-ink-muted">{description}</p>
            )}
        </div>
    );
}
