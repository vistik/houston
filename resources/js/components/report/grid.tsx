/**
 * A 12 column grid for reports. Every widget says how much of a row it takes, as a fraction or a
 * column count, and folds sensibly on smaller screens:
 *
 *     <Section title="Delivery">
 *         <MetricTile span="1/4" ... />        four to a row
 *         <ChartPanel span="2/3" ... />        a chart with a list beside it
 *         <MetricList span="1/3" ... />
 *         <Cell span="full">anything</Cell>    any other content
 *     </Section>
 *
 * The widgets live in `./widgets`.
 */
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * How much of a 12 column row a cell takes: a column count, or a fraction of the row.
 */
export type Span =
    | 1
    | 2
    | 3
    | 4
    | 5
    | 6
    | 7
    | 8
    | 9
    | 10
    | 11
    | 12
    | 'full'
    | '1/2'
    | '1/3'
    | '2/3'
    | '1/4'
    | '3/4'
    | '1/6'
    | '5/6'
    | '1/12'
    | '5/12'
    | '7/12'
    | '11/12';

export type Breakpoint = 'base' | 'sm' | 'md' | 'lg' | 'xl';

/**
 * The span per breakpoint, mobile first: a breakpoint left out keeps the span of the one below it.
 */
export type ResponsiveSpan = Partial<Record<Breakpoint, Span>>;

const BREAKPOINTS: Breakpoint[] = ['base', 'sm', 'md', 'lg', 'xl'];

// Spelled out in full so Tailwind finds every class when it scans the source.
const COLUMN_CLASSES: Record<Breakpoint, string[]> = {
    base: [
        'col-span-1',
        'col-span-2',
        'col-span-3',
        'col-span-4',
        'col-span-5',
        'col-span-6',
        'col-span-7',
        'col-span-8',
        'col-span-9',
        'col-span-10',
        'col-span-11',
        'col-span-12',
    ],
    sm: [
        'sm:col-span-1',
        'sm:col-span-2',
        'sm:col-span-3',
        'sm:col-span-4',
        'sm:col-span-5',
        'sm:col-span-6',
        'sm:col-span-7',
        'sm:col-span-8',
        'sm:col-span-9',
        'sm:col-span-10',
        'sm:col-span-11',
        'sm:col-span-12',
    ],
    md: [
        'md:col-span-1',
        'md:col-span-2',
        'md:col-span-3',
        'md:col-span-4',
        'md:col-span-5',
        'md:col-span-6',
        'md:col-span-7',
        'md:col-span-8',
        'md:col-span-9',
        'md:col-span-10',
        'md:col-span-11',
        'md:col-span-12',
    ],
    lg: [
        'lg:col-span-1',
        'lg:col-span-2',
        'lg:col-span-3',
        'lg:col-span-4',
        'lg:col-span-5',
        'lg:col-span-6',
        'lg:col-span-7',
        'lg:col-span-8',
        'lg:col-span-9',
        'lg:col-span-10',
        'lg:col-span-11',
        'lg:col-span-12',
    ],
    xl: [
        'xl:col-span-1',
        'xl:col-span-2',
        'xl:col-span-3',
        'xl:col-span-4',
        'xl:col-span-5',
        'xl:col-span-6',
        'xl:col-span-7',
        'xl:col-span-8',
        'xl:col-span-9',
        'xl:col-span-10',
        'xl:col-span-11',
        'xl:col-span-12',
    ],
};

/**
 * How a desktop span folds on smaller screens. Small tiles pair up on phones and fill thirds on
 * tablets, anything wider stacks until there is room for it. Spans not listed keep their width from
 * `lg` up and stack below it.
 */
const FOLDS: Partial<Record<number, ResponsiveSpan>> = {
    1: { base: 6, md: 3, lg: 2, xl: 1 },
    2: { base: 6, md: 4, xl: 2 },
    3: { base: 6, lg: 3 },
    12: { base: 12 },
};

export function columns(span: Span): number {
    if (typeof span === 'number') {
        return span;
    }

    if (span === 'full') {
        return 12;
    }

    const [numerator, denominator] = span.split('/').map(Number);

    return (12 / denominator) * numerator;
}

/**
 * The column classes for a span. A single span is the width on a desktop screen, folded for smaller
 * ones; pass a span per breakpoint to lay it out by hand instead.
 */
export function spanClasses(span: Span | ResponsiveSpan): string {
    const responsive: ResponsiveSpan =
        typeof span === 'object'
            ? span
            : (FOLDS[columns(span)] ?? { base: 12, lg: span });

    return BREAKPOINTS.filter((breakpoint) => responsive[breakpoint])
        .map(
            (breakpoint) =>
                COLUMN_CLASSES[breakpoint][
                    columns(responsive[breakpoint]!) - 1
                ],
        )
        .join(' ');
}

/**
 * A 12 column grid for laying out a report. Every child should be a `Cell`, or a widget that
 * renders one, saying how much of the row it takes; cells flow into the next row when one fills up.
 */
export function Grid({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('grid grid-cols-12 gap-4', className)}>
            {children}
        </div>
    );
}

/**
 * One area of a `Grid`. Its content stretches to the tallest cell in the row, so cards side by side
 * line up.
 */
export function Cell({
    span = 'full',
    children,
    className,
}: {
    span?: Span | ResponsiveSpan;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'grid min-w-0 grid-cols-1',
                spanClasses(span),
                className,
            )}
        >
            {children}
        </div>
    );
}
