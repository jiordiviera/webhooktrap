import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { ReactNode, RefObject } from 'react';
import { useLayoutEffect, useRef } from 'react';
import { Button } from './form';
import { Glyph } from './glyph';

/*
 * Security dialogs (S-17B, DS Dialog): a ruled sheet with a 1 px ink
 * border, no shadow and no blur; full-screen sheet with "Close" top left
 * at 390. Radix traps focus and closes on Esc. These dialogs open from
 * state, not from a Radix Trigger, so Radix has no opener to go back to;
 * the element focused when the dialog opened is kept here and gets focus
 * back on close, unless `returnFocus` names another target (a section
 * heading after a removal, A11Y §9).
 */

function useOpener(open: boolean) {
    const opener = useRef<HTMLElement | null>(null);

    // Layout effect: runs before Radix moves focus into the dialog.
    useLayoutEffect(() => {
        if (open) {
            const active = document.activeElement;
            opener.current =
                active instanceof HTMLElement && active !== document.body
                    ? active
                    : null;
        }
    }, [open]);

    return opener;
}

export function SecurityDialog({
    open,
    onOpenChange,
    title,
    children,
    footer,
    destructive = false,
    returnFocus,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    children: ReactNode;
    footer?: ReactNode;
    destructive?: boolean;
    returnFocus?: RefObject<HTMLElement | null>;
}) {
    const opener = useOpener(open);

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="tt-overlay" />
                <DialogPrimitive.Content
                    className="tt tt-dialog"
                    role={destructive ? 'alertdialog' : 'dialog'}
                    aria-modal="true"
                    aria-describedby={undefined}
                    onOpenAutoFocus={(event) => {
                        // Focus to the title on open (A11Y §9 Security dialogs).
                        event.preventDefault();
                        const content =
                            event.currentTarget as HTMLElement | null;
                        content?.querySelector<HTMLElement>('h2')?.focus();
                    }}
                    onCloseAutoFocus={(event) => {
                        const target = returnFocus?.current?.isConnected
                            ? returnFocus.current
                            : opener.current?.isConnected
                              ? opener.current
                              : null;

                        if (target) {
                            event.preventDefault();
                            target.focus();
                        }
                    }}
                >
                    <header>
                        <DialogPrimitive.Close asChild>
                            <Button variant="ghost" className="tt-close-m">
                                <Glyph name="close" />
                                Close
                            </Button>
                        </DialogPrimitive.Close>
                        <DialogPrimitive.Title asChild>
                            <h2 tabIndex={-1}>{title}</h2>
                        </DialogPrimitive.Title>
                    </header>
                    <div className="tt-body">{children}</div>
                    {footer && <footer>{footer}</footer>}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

export const DialogCancel = DialogPrimitive.Close;
