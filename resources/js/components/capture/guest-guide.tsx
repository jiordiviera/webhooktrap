import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import ExampleRun from '@/actions/App/Modules/Proving/Http/Controllers/ExampleRunController';
import { Glyph } from '@/components/identity/glyph';
import { guestCopy, guestSignInUrl } from '@/lib/identity/guest';

/*
 * Guest guidance on S-03 (issue #18): the persistent delivery-quota banner
 * (FL-11, RD `copy.capture.quota`) and, after the first delivery, the next
 * step towards a first run (pair the agent, press Prove).
 */

/** S-03 `quota`: persistent, not dismissible; the list stays usable. */
export function GuestQuotaBanner() {
    const { principal, meter, currentProject } = usePage().props;

    if (
        principal.kind !== 'guest' ||
        !meter ||
        meter.captures.used < meter.captures.limit
    ) {
        return null;
    }

    return (
        <div className="tt-bannerx" role="status">
            <p className="txt">
                <Glyph name="info" />
                <span>
                    <strong>
                        {guestCopy.quota.label(meter.captures.limit)}
                    </strong>{' '}
                    {guestCopy.quota.helper}
                </span>
            </p>
            <Link className="tt-btn" href={guestSignInUrl(currentProject?.key)}>
                Sign in to keep
            </Link>
        </div>
    );
}

const DISMISS_KEY = 'wt.guest.next-step';

function readDismissed(key: string | undefined): boolean {
    try {
        return key !== undefined && sessionStorage.getItem(DISMISS_KEY) === key;
    } catch {
        return false;
    }
}

/**
 * After the first delivery and before the first run: what to do next. It
 * follows the agent pill (no agent: pair it; paired: press Prove).
 */
export function GuestNextStep() {
    const { principal, meter, agentPill, currentProject } = usePage().props;
    const key = currentProject?.key;
    const [dismissed, setDismissed] = useState(() => readDismissed(key));

    if (
        principal.kind !== 'guest' ||
        !meter ||
        meter.runs.used > 0 ||
        dismissed ||
        !key
    ) {
        return null;
    }
    const paired =
        agentPill !== undefined &&
        agentPill !== null &&
        agentPill.state !== 'none';
    const copy = paired ? guestCopy.next.paired : guestCopy.next.unpaired;

    return (
        <aside className="tt-bannerx tt-nextstep" aria-labelledby="h-next-step">
            <div className="txt">
                <Glyph name="right" />
                <span>
                    <strong id="h-next-step">{copy.label}</strong> {copy.helper}
                </span>
            </div>
            {!paired && (
                <Link className="tt-btn" href={AgentPage.guest.url(key)}>
                    Pair the agent
                </Link>
            )}
            <Link href={ExampleRun.show.url()}>See an example run ›</Link>
            <button
                type="button"
                className="tt-iconbtn"
                aria-label="Hide this tip"
                onClick={() => {
                    setDismissed(true);

                    try {
                        sessionStorage.setItem(DISMISS_KEY, key);
                    } catch {
                        // Private mode: the tip simply comes back on reload.
                    }
                }}
            >
                <Glyph name="close" />
            </button>
        </aside>
    );
}
