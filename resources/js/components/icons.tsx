import { useId } from 'react';
import type { SVGProps } from 'react';

export type IconProps = SVGProps<SVGSVGElement>;

export function Icon({ children, ...props }: IconProps) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            width={16}
            height={16}
            aria-hidden
            {...props}
        >
            {children}
        </svg>
    );
}

export const RefreshIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M21 12a9 9 0 1 1-2.64-6.36" />
        <path d="M21 3v6h-6" />
    </Icon>
);

export const ArrowUpIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M12 19V5M5 12l7-7 7 7" />
    </Icon>
);

export const ArrowDownIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M12 5v14M19 12l-7 7-7-7" />
    </Icon>
);

export const ExternalIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M14 4h6v6M20 4 10 14M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5" />
    </Icon>
);

export const LockIcon = (props: IconProps) => (
    <Icon {...props}>
        <rect x="5" y="11" width="14" height="10" rx="2" />
        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </Icon>
);

export const PlusIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M12 5v14M5 12h14" />
    </Icon>
);

export const TrashIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
    </Icon>
);

export const SunIcon = (props: IconProps) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </Icon>
);

export const MoonIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" />
    </Icon>
);

export const CheckIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="m5 12 5 5L20 7" />
    </Icon>
);

export const XIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M6 6l12 12M18 6 6 18" />
    </Icon>
);

export const DotIcon = (props: IconProps) => (
    <Icon {...props}>
        <circle cx="12" cy="12" r="4" fill="currentColor" stroke="none" />
    </Icon>
);

export const ChevronLeftIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="m15 18-6-6 6-6" />
    </Icon>
);

export const ChevronDownIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="m6 9 6 6 6-6" />
    </Icon>
);

export const EllipsisIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M5 12h.01M12 12h.01M19 12h.01" strokeWidth={2.5} />
    </Icon>
);

export const MonitorIcon = (props: IconProps) => (
    <Icon {...props}>
        <rect x="2" y="4" width="20" height="13" rx="2" />
        <path d="M8 21h8M12 17v4" />
    </Icon>
);

export const StarIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9Z" />
    </Icon>
);

export const AlertIcon = (props: IconProps) => (
    <Icon {...props}>
        <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
    </Icon>
);

/**
 * A planet with a spacecraft in orbit: the orbit passes behind the planet, and the stretch the craft has flown glows green.
 */
export function LogoMark(props: IconProps) {
    const maskId = `logo-${useId().replace(/[^a-zA-Z0-9-]/g, '')}`;

    return (
        <svg
            viewBox="0 0 32 32"
            width={22}
            height={22}
            fill="none"
            strokeLinecap="round"
            aria-hidden
            {...props}
        >
            <defs>
                <mask
                    id={maskId}
                    maskUnits="userSpaceOnUse"
                    x="0"
                    y="0"
                    width="32"
                    height="32"
                >
                    <rect width="32" height="32" fill="#fff" />
                    <circle cx="16" cy="16" r="8.2" fill="#000" />
                </mask>
            </defs>
            <ellipse
                cx="16"
                cy="16"
                rx="13.5"
                ry="5.5"
                transform="rotate(-25 16 16)"
                stroke="var(--logo-track)"
                strokeWidth="2"
                mask={`url(#${maskId})`}
            />
            <circle
                cx="16"
                cy="16"
                r="6.6"
                fill="var(--accent)"
                fillOpacity="0.12"
                stroke="var(--accent)"
                strokeWidth="2.4"
            />
            <path
                d="M3.77 21.71A13.5 5.5 -25 0 0 21.99 18.98"
                stroke="var(--logo-rise)"
                strokeWidth="2.6"
            />
            <circle cx="25.65" cy="16.15" r="2.1" fill="var(--logo-rise)" />
        </svg>
    );
}
