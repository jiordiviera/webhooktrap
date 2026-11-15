import { useMemo } from 'react';
import { encodeQr } from '@/lib/identity/qr';

/** QR for the authenticator setup URI (S-17B `totp`), 200 × 200, dark on paper. */
export function QrCode({
    value,
    alt,
    size = 200,
}: {
    value: string;
    alt: string;
    size?: number;
}) {
    const path = useMemo(() => {
        const matrix = encodeQr(value);
        let d = '';

        matrix.forEach((row, y) => {
            row.forEach((dark, x) => {
                if (dark) {
                    d += `M${x + 4} ${y + 4}h1v1h-1z`;
                }
            });
        });

        return { d, size: matrix.length + 8 };
    }, [value]);

    return (
        <span className="tt-qrbox">
            <svg
                role="img"
                aria-label={alt}
                width={size}
                height={size}
                viewBox={`0 0 ${path.size} ${path.size}`}
                shapeRendering="crispEdges"
            >
                <rect width={path.size} height={path.size} fill="#f7f7f4" />
                <path d={path.d} fill="#1c1b15" />
            </svg>
        </span>
    );
}
