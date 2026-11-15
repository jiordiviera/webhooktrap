import { GitHubLogo, GoogleLogo } from '@/components/brand/provider-logos';
import { oauth } from '@/routes/sign-in';
import { buttonClass } from './form';

/** The svgl.app mark for a sign-in provider, sized like a 16 px glyph. */
export function ProviderLogo({
    provider,
    className = 'tt-g tt-g-16',
}: {
    provider: string;
    className?: string;
}) {
    if (provider === 'github') {
        return <GitHubLogo className={className} />;
    }

    return provider === 'google' ? <GoogleLogo className={className} /> : null;
}

/*
 * "Continue with GitHub / Google" (S-02 §3, S-24): full width, 44 tall,
 * the provider's svgl.app mark at left (GitHub follows the text colour). The OAuth round trip
 * leaves the app, so these are plain links, not Inertia visits.
 */
export function ProviderButtons({ describedBy }: { describedBy?: string }) {
    const cls = `${buttonClass({ size: 'lg', block: true })} tt-pbtn`;

    return (
        <div
            className="tt-col"
            style={{ gap: 10 }}
            aria-describedby={describedBy}
        >
            <a className={cls} href={oauth.url('github')}>
                <ProviderLogo provider="github" />
                Continue with GitHub
            </a>
            <a className={cls} href={oauth.url('google')}>
                <ProviderLogo provider="google" />
                Continue with Google
            </a>
        </div>
    );
}
