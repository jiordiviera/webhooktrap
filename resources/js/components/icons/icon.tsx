import type { ReactNode, SVGAttributes } from 'react';

/**
 * Timetable icons (DS v16): 16px grid, 1.5px stroke, square caps, mitred joins.
 * Paint in currentColor, so they follow the theme. Decorative by default
 * (aria-hidden); pass `aria-label` to expose one to assistive tech.
 */
const glyphs = {
    agent: (
        <>
            <circle
                cx="8"
                cy="4"
                r="2.25"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
            <path
                d="M8 6.5V10M5 10H11V14H5z"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    burst: (
        <>
            <path
                d="M3 3 13 13M3 3V13M3 3H13"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    late: (
        <>
            <path
                d="M6.5 1.5V14.5M2 3 13 14"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    once: (
        <>
            <path
                d="M3 3 13 13"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    prove: (
        <>
            <path
                d="M2 2 13 13M13 8V13H8M2.5 4H5.5M5.5 7H8.5M8.5 10H11"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    retry: (
        <>
            <path
                d="M2 3 7 13M9 3 14 13M7 13H9"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    shuffled: (
        <>
            <path
                d="M3 3 13 13M13 3 3 13"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    signature: (
        <>
            <circle
                cx="8"
                cy="8"
                r="6"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
            <circle cx="8" cy="8" r="2.75" fill="currentColor" />
        </>
    ),
    'slow-budget': (
        <>
            <path
                d="M2 11.5H10V2M2 3.5 6 7.5H14"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    tampered: (
        <>
            <path
                d="M2 2 6 6H10L14 10"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    twice: (
        <>
            <path
                d="M1.5 3 11.5 13M5.5 3 15.5 13"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
    unsigned: (
        <>
            <path
                d="M2 2 14 14"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
            <circle
                cx="8"
                cy="8"
                r="2.75"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="square"
                strokeLinejoin="miter"
                fill="none"
            />
        </>
    ),
} satisfies Record<string, ReactNode>;

export type IconName = keyof typeof glyphs;

export const iconNames = Object.keys(glyphs) as IconName[];

export type IconProps = Omit<SVGAttributes<SVGSVGElement>, 'name'> & {
    name: IconName;
    size?: number;
    'aria-label'?: string;
};

export default function Icon({
    name,
    size = 16,
    'aria-label': ariaLabel,
    ...props
}: IconProps) {
    const labelled = ariaLabel !== undefined;

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={size}
            height={size}
            viewBox="0 0 16 16"
            focusable="false"
            {...(labelled
                ? { role: 'img', 'aria-label': ariaLabel }
                : { 'aria-hidden': true })}
            {...props}
        >
            {glyphs[name]}
        </svg>
    );
}
