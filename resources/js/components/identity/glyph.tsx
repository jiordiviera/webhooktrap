import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/*
 * Timetable glyphs used on the Identity screens (DS sprite, 16-unit grid,
 * 1.5 stroke, square caps). Always next to a word, so always decorative.
 * Brand marks are not glyphs: they come from components/brand.
 */

const stroke = {
    stroke: 'currentColor',
    strokeWidth: 1.5,
    strokeLinecap: 'square',
    strokeLinejoin: 'miter',
    fill: 'none',
} as const;

const glyphs = {
    key: (
        <>
            <circle cx="5" cy="8" r="2.75" {...stroke} />
            <path d="M7.75 8H14M12 8V11M14 8V10.5" {...stroke} />
        </>
    ),
    info: <circle cx="8" cy="8" r="4.75" {...stroke} />,
    check: <path d="M3 8.5 6.5 12 13 4.5" {...stroke} />,
    close: <path d="M3.5 3.5 12.5 12.5M12.5 3.5 3.5 12.5" {...stroke} />,
    lock: <path d="M4 7.5H12V14H4zM6 7.5V4.5H10V7.5" {...stroke} />,
    copy: <path d="M5.5 5.5H13.5V13.5H5.5zM2.5 10.5V2.5H10.5" {...stroke} />,
    left: <path d="M10 4 6 8 10 12" {...stroke} />,
    right: <path d="M6 4 10 8 6 12" {...stroke} />,
    down: <path d="M4 6 8 10 12 6" {...stroke} />,
    wait: <path d="M8 2.5A5.5 5.5 0 1 1 2.5 8" {...stroke} />,
    blocked: <path d="M2 8H10M11.5 4V12" {...stroke} />,
    error: (
        <>
            <circle cx="8" cy="8" r="6" {...stroke} />
            <path d="M5.2 5.2 10.8 10.8M10.8 5.2 5.2 10.8" {...stroke} />
        </>
    ),
    more: (
        <>
            <rect x="2" y="7" width="2.5" height="2.5" fill="currentColor" />
            <rect x="6.75" y="7" width="2.5" height="2.5" fill="currentColor" />
            <rect x="11.5" y="7" width="2.5" height="2.5" fill="currentColor" />
        </>
    ),
} satisfies Record<string, ReactNode>;

export type GlyphName = keyof typeof glyphs;

export function Glyph({
    name,
    large = false,
    spin = false,
    className,
}: {
    name: GlyphName;
    large?: boolean;
    spin?: boolean;
    className?: string;
}) {
    return (
        <svg
            className={cn(
                'tt-g',
                large && 'tt-g-16',
                spin && 'tt-spin',
                className,
            )}
            viewBox="0 0 16 16"
            aria-hidden="true"
            focusable="false"
        >
            {glyphs[name]}
        </svg>
    );
}
