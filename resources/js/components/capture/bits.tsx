import type { ComponentType, SVGAttributes } from 'react';
import {
    GitHubLogo,
    ShopifyLogo,
    StripeLogo,
} from '@/components/brand/provider-logos';
import { StatusBadge } from '@/components/identity/form';
import { captureCopy, signatureCopy, splitEscapes } from '@/lib/capture/format';
import { useLiveStatus } from '@/lib/realtime/echo';
import type { SignatureChip } from '@/types/capture';

/** Untrusted text: always text nodes; server escapes styled when flagged. */
export function EscText({
    text,
    escaped = false,
}: {
    text: string;
    escaped?: boolean;
}) {
    if (!escaped) {
        return <>{text}</>;
    }

    return (
        <>
            {splitEscapes(text).map((part, i) =>
                part.escape ? (
                    <span key={i} className="tt-esc">
                        {part.text}
                    </span>
                ) : (
                    <span key={i}>{part.text}</span>
                ),
            )}
        </>
    );
}

const LOGOS: Record<string, ComponentType<SVGAttributes<SVGElement>>> = {
    stripe: StripeLogo,
    github: GitHubLogo,
    shopify: ShopifyLogo,
};

const NAMES: Record<string, string> = {
    stripe: 'Stripe',
    github: 'GitHub',
    shopify: 'Shopify',
    standard_webhooks: 'Standard Webhooks',
    curl: 'curl',
};

export function providerName(id: string | null): string {
    return id ? (NAMES[id] ?? id) : 'Unknown';
}

/**
 * svgl mark (decorative) + name. `name` is the sender's display name from
 * the pack (`Parcelbird` on Standard Webhooks); a provider without a svgl
 * mark (scheme packs, Unknown) gets a neutral glyph.
 */
export function ProviderMark({
    provider,
    name,
    showName = true,
}: {
    provider: string | null;
    name?: string | null;
    showName?: boolean;
}) {
    const Logo = provider ? LOGOS[provider] : undefined;
    const label = name ?? providerName(provider);

    return (
        <span className="tt-prov">
            {Logo ? (
                <Logo className="tt-g tt-g-16" />
            ) : (
                <span
                    className={`tt-prov-dot${provider ? ' is-scheme' : ''}`}
                    aria-hidden="true"
                />
            )}
            {showName ? (
                <span>
                    <EscText text={label} escaped />
                </span>
            ) : (
                <span className="tt-sr">{label}</span>
            )}
        </span>
    );
}

/** Signature in words (A11Y: never colour or glyph alone). */
export function SignatureWord({ signature }: { signature: SignatureChip }) {
    const label = signatureCopy[signature.state].label;
    const tone =
        signature.state === 'valid'
            ? 'pass'
            : signature.state === 'invalid'
              ? 'fail'
              : signature.state === 'no_secret' ||
                  signature.state === 'unknown_scheme'
                ? 'warn'
                : 'off';

    // PASS/FAIL badges only where the state is a verdict; others in words.
    return (
        <span className="st">
            {(signature.state === 'valid' || signature.state === 'invalid') && (
                <StatusBadge tone={tone}>
                    {signature.state === 'valid' ? 'PASS' : 'FAIL'}
                </StatusBadge>
            )}
            <span>{label}</span>
        </span>
    );
}

/** Live / Connecting / Paused, in the capture header. */
export function LiveIndicator() {
    const status = useLiveStatus();
    const label =
        status === 'live'
            ? captureCopy.live
            : status === 'connecting'
              ? captureCopy.connecting
              : status === 'paused'
                ? captureCopy.streamLost
                : captureCopy.paused;

    return (
        <span className="tt-live" data-status={status} role="status">
            <span className="dot" aria-hidden="true" />
            {label}
        </span>
    );
}
