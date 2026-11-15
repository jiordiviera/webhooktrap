import {
    GitHubLogo,
    ShopifyLogo,
    StripeLogo,
} from '@/components/brand/provider-logos';
import { RunExcerpt } from '@/components/landing/run-terminal';

/*
 * Side panel of the auth shell: our own proof, no testimonials or customer
 * logos (we have none). A static run excerpt ending on the F2 finding, a
 * caption, and the providers we work with. Decorative and identical on every
 * auth screen, so the whole panel is hidden from assistive tech and holds
 * nothing focusable. Hidden below 1024 px.
 */
export function AuthProof() {
    return (
        <aside className="tt-proof lp-tokens" aria-hidden="true">
            <div className="tt-proof-run">
                <RunExcerpt />
                {/* COPY PROPOSAL (A07, PO auth direction): caption. */}
                <p className="tt-proof-cap">
                    Your handler returned 200. The database disagreed.
                </p>
            </div>
            <div className="tt-proof-works">
                <p>Works with</p>
                <ul>
                    <li>
                        <StripeLogo className="size-4" />
                        Stripe
                    </li>
                    <li>
                        <GitHubLogo className="size-4" />
                        GitHub
                    </li>
                    <li>
                        <ShopifyLogo className="size-4" />
                        Shopify
                    </li>
                    <li>Standard Webhooks</li>
                </ul>
            </div>
        </aside>
    );
}
