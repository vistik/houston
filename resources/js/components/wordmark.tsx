import { LogoMark } from '@/components/icons';
import { cn } from '@/lib/utils';

/** The headline on the landing page, which the wordmark carries as its subheader. */
export const TAGLINE = 'Mission control for your engineering team';

/**
 * The logo and name, with the tagline under them unless `tagline` is off, so Houston reads the same
 * wherever it introduces itself.
 */
export function Wordmark({
    tagline = true,
    className,
}: {
    tagline?: boolean;
    className?: string;
}) {
    return (
        <span className={cn('flex items-center gap-2', className)}>
            <LogoMark />
            <span className="flex flex-col">
                <span className="leading-tight font-semibold tracking-tight">
                    Houston
                </span>
                {tagline && (
                    <span className="hidden text-[11px] leading-tight text-ink-muted sm:block">
                        {TAGLINE}
                    </span>
                )}
            </span>
        </span>
    );
}
