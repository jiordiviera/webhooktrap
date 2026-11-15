import type { SVGAttributes } from 'react';

/**
 * Twice mark (Timetable DS). Lines follow `currentColor`; the square is the
 * conflict token, so the mark follows both themes. Minimum size 16px.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            {...props}
            viewBox="0 0 16 16"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M0 3H16M0 12H12M1 3L10 12M6 3L14 11"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="miter"
                strokeMiterlimit="4"
            />
            <rect x="12" y="10" width="4" height="4" fill="var(--conflict)" />
        </svg>
    );
}
