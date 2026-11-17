import { Link } from '@inertiajs/react';
import { useState } from 'react';
import ExampleRun from '@/actions/App/Modules/Proving/Http/Controllers/ExampleRunController';
import { CaptureUrlChip } from '@/components/shell/app-shell';
import { Glyph } from '@/components/identity/glyph';
import {
    captureCopy,
    fromYourApp,
    providerInstructions,
    SAMPLE_BODY,
} from '@/lib/capture/format';
import { LiveIndicator, ProviderMark } from './bits';

/*
 * S-03 `waiting`: one column (max 720). The URL large with Copy, provider
 * tabs (Stripe · GitHub · Shopify · Standard Webhooks · curl), the live
 * mark, and Send a test request. The curl example uses the real URL.
 */
export function WaitingPanel({
    captureUrl,
    providers,
}: {
    captureUrl: string | null;
    providers: { id: string; name: string }[];
}) {
    const [tab, setTab] = useState(providers[0]?.id ?? 'curl');
    const [sent, setSent] = useState<'idle' | 'sending' | 'sent' | 'failed'>(
        'idle',
    );
    const url = captureUrl ?? '';

    const sendTest = async () => {
        if (!captureUrl || sent === 'sending') {
            return;
        }
        setSent('sending');

        try {
            // A simple request (text/plain): no preflight, no credentials.
            await fetch(captureUrl, {
                method: 'POST',
                mode: 'no-cors',
                credentials: 'omit',
                headers: { 'Content-Type': 'text/plain' },
                body: SAMPLE_BODY,
            });
            setSent('sent');
        } catch {
            setSent('failed');
        }
    };

    return (
        <section className="tt-waiting" aria-labelledby="h-waiting">
            <div className="tt-sec">
                <h2 id="h-waiting" className="tt-rowhead">
                    <span>{captureCopy.waiting}</span>
                    <LiveIndicator />
                </h2>
                <p>{captureCopy.waitingHelper}</p>
                <div className="tt-waiting-url">
                    <CaptureUrlChip url={captureUrl} className="is-large" />
                </div>
                <div
                    role="tablist"
                    aria-label="Provider instructions"
                    className="tt-tabs"
                >
                    {providers.map((p, i) => (
                        <button
                            key={p.id}
                            type="button"
                            role="tab"
                            id={`wtab-${p.id}`}
                            aria-controls="wpanel"
                            aria-selected={tab === p.id}
                            tabIndex={tab === p.id ? 0 : -1}
                            className="tt-tab"
                            onClick={() => setTab(p.id)}
                            onKeyDown={(event) => {
                                const step =
                                    event.key === 'ArrowRight'
                                        ? 1
                                        : event.key === 'ArrowLeft'
                                          ? -1
                                          : 0;

                                if (step) {
                                    event.preventDefault();
                                    const next =
                                        providers[
                                            (i + step + providers.length) %
                                                providers.length
                                        ];
                                    setTab(next.id);
                                    document
                                        .getElementById(`wtab-${next.id}`)
                                        ?.focus();
                                }
                            }}
                        >
                            {p.id === 'curl' ? (
                                'curl'
                            ) : (
                                <ProviderMark provider={p.id} />
                            )}
                        </button>
                    ))}
                </div>
                <div
                    role="tabpanel"
                    id="wpanel"
                    aria-labelledby={`wtab-${tab}`}
                    className="tt-tabpanel"
                >
                    {tab === 'curl' ? (
                        <pre className="tt-code" data-wrap="on" tabIndex={0}>
                            <span className="ln">
                                <span className="t">
                                    {providerInstructions('curl', url)}
                                </span>
                            </span>
                        </pre>
                    ) : (
                        <p>{providerInstructions(tab, url)}</p>
                    )}
                </div>
                <p className="tt-hint">{fromYourApp}</p>
                <div className="tt-row" style={{ flexWrap: 'wrap', gap: 12 }}>
                    <button
                        type="button"
                        className="tt-btn"
                        aria-describedby="send-test-help"
                        aria-disabled={
                            !captureUrl || sent === 'sending' || undefined
                        }
                        onClick={() => void sendTest()}
                    >
                        {captureCopy.sendTest}
                    </button>
                    <span className="tt-hint" id="send-test-help">
                        {captureCopy.sendTestHelper}
                    </span>
                    <Link href={ExampleRun.show.url()}>
                        See an example run ›
                    </Link>
                    <span className="tt-wait" role="status">
                        {sent === 'sent' && (
                            <>
                                <Glyph name="check" />
                                {/* COPY PROPOSAL (A07) */}
                                Sent. It appears here in a second.
                            </>
                        )}
                        {sent === 'failed' &&
                            "Couldn't send from this browser. Use the curl command."}
                    </span>
                </div>
            </div>
        </section>
    );
}
