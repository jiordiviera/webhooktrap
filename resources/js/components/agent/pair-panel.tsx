import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PairingCodes from '@/actions/App/Modules/Agent/Http/Controllers/PairingCodeController';
import { Glyph } from '@/components/identity/glyph';
import { postJson } from '@/lib/identity/http';
import type { AgentRow, AgentSummary, PairingCodeView } from '@/types/run';

/*
 * S-06/S-08 pair panel: a one-time code from `POST /pairing-codes`, the
 * command to run (`webhooktrap pair <CODE>`), a countdown, then "Confirm in
 * your terminal" until the agent is confirmed. Pages that carry agent props
 * pass `reloadOnly` so the panel can see the agent arrive (no channel for it
 * yet); elsewhere the panel shows the code and the next step only.
 */

const POLL_MS = 3000;

function mmss(ms: number): string {
    const s = Math.max(0, Math.floor(ms / 1000));

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** The command people type. Off the hosted origin it names this server. */
export function pairCommand(code: string): string {
    const origin = typeof window === 'undefined' ? '' : window.location.origin;
    const hosted = origin === 'https://webhooktrap.dev';

    return `webhooktrap pair ${code}${hosted || !origin ? '' : ` --server ${origin}`}`;
}

const errorCopy: Record<string, string> = {
    throttled: 'Too many codes in a few minutes. Try again shortly.',
    agent_limit:
        'This workspace has the most agents it can hold. Remove one first.',
};

export function PairPanel({
    agent: given,
    agents,
    replace = null,
    reloadOnly,
}: {
    agent: AgentSummary | null;
    /** Agent pages: the new agent is the one paired after the code. */
    agents?: AgentRow[];
    /** `/agents/pair?replace=agt_…`: the code replaces that agent. */
    replace?: string | null;
    reloadOnly?: string[];
}) {
    const [code, setCode] = useState<PairingCodeView | null>(null);
    const [issuedAt, setIssuedAt] = useState(0);
    const fresh =
        agents && issuedAt > 0
            ? ([...agents]
                  .filter(
                      (a) =>
                          a.id !== replace &&
                          new Date(a.pairedAt).getTime() >= issuedAt - 5000,
                  )
                  .sort((a, b) => b.pairedAt.localeCompare(a.pairedAt))[0] ??
              null)
            : null;
    // Without a code on screen (a reload), an agent already waiting for its
    // terminal confirmation is still the one to talk about.
    const waitingAgent =
        agents?.find(
            (a) => a.id !== replace && a.state === 'awaiting_confirmation',
        ) ?? null;
    const agent = agents ? (fresh ?? waitingAgent) : given;
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [now, setNow] = useState(() => Date.now());
    const [copied, setCopied] = useState(false);
    const left = code ? new Date(code.expiresAt).getTime() - now : 0;
    const expired = code !== null && left <= 0;
    const waiting = code !== null && !expired && agent?.state !== 'connected';

    useEffect(() => {
        if (!code) {
            return;
        }
        const t = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(t);
    }, [code]);

    useEffect(() => {
        if (!waiting || !reloadOnly) {
            return;
        }
        const t = window.setInterval(
            () => router.reload({ only: reloadOnly }),
            POLL_MS,
        );

        return () => window.clearInterval(t);
    }, [waiting, reloadOnly]);

    const issue = async () => {
        setBusy(true);
        setError(null);
        const answer = await postJson<PairingCodeView>(
            PairingCodes.store.url(),
            replace ? { replace } : {},
        );
        setBusy(false);

        if (answer.ok) {
            setCode(answer.data);
            setNow(Date.now());
            setIssuedAt(Date.now());
        } else {
            setError(
                errorCopy[answer.error] ??
                    "Couldn't get a pairing code. Try again.",
            );
        }
    };

    if (agent?.state === 'connected') {
        return (
            <div className="tt-pair" data-state="connected">
                <p className="tt-pair-state">
                    <span className="tt-badge is-pass" aria-hidden="true">
                        ON
                    </span>
                    Connected · {agent.name}
                </p>
                <p className="tt-muted">Agent {agent.version}</p>
            </div>
        );
    }

    if (
        agent?.state === 'awaiting_confirmation' ||
        agent?.state === 'needs_reconfirm'
    ) {
        return (
            <div className="tt-pair" data-state="confirm" role="status">
                <p className="tt-pair-state">
                    <Glyph name="wait" spin />
                    Confirm in your terminal
                </p>
                <p>
                    {agent.state === 'needs_reconfirm'
                        ? `This session moved to your account, so ${agent.name} asks you to confirm the new workspace in its terminal. It runs nothing until you do.`
                        : `${agent.name} is waiting for you to type y in the terminal where you ran the command. Check that it shows ${typeof window === 'undefined' ? 'this server' : window.location.origin} first.`}
                </p>
            </div>
        );
    }

    return (
        <div className="tt-pair">
            <p className="tt-pair-state">
                {agent?.state === 'disconnected'
                    ? `${agent.name} is offline`
                    : 'No agent connected'}
            </p>
            <p className="tt-muted">
                The agent runs on your machine, sends deliveries to localhost
                and keeps your signing secrets there.
            </p>
            {code && !expired ? (
                <>
                    <div className="tt-paircode">
                        <span className="tt-sr">Pairing code </span>
                        <span className="code tt-mono">{code.code}</span>
                        <span className="tt-muted" aria-live="off">
                            expires in {mmss(left)}
                        </span>
                    </div>
                    <div className="tt-chip is-command">
                        <span aria-hidden="true" className="ps">
                            $
                        </span>
                        <code>{pairCommand(code.code)}</code>
                        <button
                            type="button"
                            className="tt-chipbtn"
                            aria-label="Copy the pair command"
                            onClick={() =>
                                void navigator.clipboard
                                    .writeText(pairCommand(code.code))
                                    .then(() => setCopied(true))
                                    .catch(() => setCopied(false))
                            }
                        >
                            <Glyph name={copied ? 'check' : 'copy'} />
                        </button>
                    </div>
                    <p className="tt-wait" role="status">
                        <Glyph name="wait" spin />
                        Waiting for your agent… Run the command in a terminal.
                    </p>
                </>
            ) : (
                <div className="tt-row" style={{ gap: 12, flexWrap: 'wrap' }}>
                    <button
                        type="button"
                        className="tt-btn tt-btn-primary"
                        aria-busy={busy || undefined}
                        onClick={() => !busy && void issue()}
                    >
                        {expired ? 'Get a new code' : 'Pair an agent'}
                    </button>
                    {expired && (
                        <span className="tt-muted">
                            {/* COPY PROPOSAL (A07) */}
                            That code expired.
                        </span>
                    )}
                </div>
            )}
            {error && (
                <p className="tt-ferr" role="alert">
                    <Glyph name="error" />
                    <span>{error}</span>
                </p>
            )}
        </div>
    );
}
