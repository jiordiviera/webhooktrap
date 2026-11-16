import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';

/* Toasts are drawn by lib/notify.tsx; this only places the stack. */
function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-left"
            visibleToasts={2}
            gap={10}
            toastOptions={{ unstyled: true }}
            style={{ '--width': '380px' } as React.CSSProperties}
            {...props}
        />
    );
}

export { Toaster };
