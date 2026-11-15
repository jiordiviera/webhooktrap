import { useEffect, useState } from 'react';
import { webauthnSupported } from '@/lib/identity/webauthn';

/**
 * Whether this browser can run WebAuthn. Server render assumes yes, the
 * browser checks after hydration, so both renders agree.
 */
export function useWebAuthnSupport(): boolean {
    const [supported, setSupported] = useState(true);

    useEffect(() => {
        setSupported(webauthnSupported());
    }, []);

    return supported;
}
