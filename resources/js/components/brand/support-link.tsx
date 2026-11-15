import type { AnchorHTMLAttributes, ReactNode } from 'react';
import { BuyMeACoffeeLogo } from './provider-logos';

/*
 * The "Buy me a coffee" support link (PO). One component for the landing,
 * the app shell's ⋯ menu, Preferences and the system pages: external, new
 * tab, an accessible name that says so, and the svgl cup beside the text.
 * Props are spread last so a menu item (asChild) can pass its own.
 */

export const SUPPORT_URL = 'https://buymeacoffee.com/jiordiviera';

export function SupportLink({
    children = 'Buy me a coffee',
    markClassName = 'size-3.5 shrink-0',
    className,
    ...props
}: AnchorHTMLAttributes<HTMLAnchorElement> & {
    children?: ReactNode;
    markClassName?: string;
}) {
    return (
        <a
            href={SUPPORT_URL}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Buy me a coffee (opens in a new tab)"
            className={`inline-flex items-center gap-1.5 ${className ?? ''}`}
            {...props}
        >
            <BuyMeACoffeeLogo className={markClassName} />
            <span>{children}</span>
        </a>
    );
}
