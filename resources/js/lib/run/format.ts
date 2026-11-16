import type { Provenance, Severity } from '@/types/run';

/*
 * Run page formatting and copy (S-11, S-14; RD `copy.run`, `copy.provenance`).
 * Lines marked COPY PROPOSAL (A07) are not in the copy deck yet.
 */

/** "11 840" with thin groups, as RD prints numbers. */
export const groupDigits = (n: number): string =>
    String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

export const ms = (n: number): string => `${groupDigits(n)} ms`;

/** "43.2 s" */
export const seconds = (n: number): string => `${(n / 1000).toFixed(1)} s`;

export const SEVERITY: Record<
    Severity,
    { word: string; glyph: string; tone: string }
> = {
    critical: { word: 'Critical', glyph: '■', tone: 'crit' },
    high: { word: 'High', glyph: '▲', tone: 'high' },
    medium: { word: 'Medium', glyph: '△', tone: 'med' },
    info: { word: 'Info', glyph: '○', tone: 'info' },
};

export function provenanceLine(p: Provenance): string {
    if (p.executor === 'ci') {
        return `Uploaded from CI (token ${p.ciTokenName ?? 'unknown'})`;
    }

    if (p.executor !== 'agent') {
        return 'Run by the Webhook Trap server runner';
    }

    return `Run by ${p.agentName ?? 'the agent'} from the ${p.source === 'cli' ? 'CLI' : 'web app'}`;
}

export const provenanceHelper =
    'Results come from the machine that ran them. Webhook Trap re-checks status codes and timings only.';

export const runCopy = {
    /** `total` is null on real runs until the agent's plan is known. */
    running: (
        d: number,
        total: number | null,
        scenario: string,
        elapsed: string,
    ) =>
        `Running · D${d}${total === null ? '' : ` of ${total}`} · ${scenario} · ${elapsed} elapsed`,
    preparing: 'Running · preparing: reset and probe check',
    /** COPY PROPOSAL (A07): replay controls on the example. */
    replay: 'Replay',
    pause: 'Pause',
    resume: 'Resume',
    /** COPY PROPOSAL (A07): announced once when the replay ends. */
    replayDone: (summary: string) => `Replay finished. ${summary}.`,
    notInRun: (kind: 'D' | 'F', n: number, total: number) =>
        `${kind}${n} isn't in this run (${total} ${kind === 'D' ? 'deliveries' : 'findings'}).`,
    infoNotCounted: 'Info · not counted',
    stateNotMeasured: 'Not measured: read once at the end of the burst.',
    sameRequest: 'Same request, same response, a different state.',
};

/* ---- Real runs (agent connection notes; RD `copy.run`, `copy.agent`) ---- */

/** Reason sentences per RUN-1 status; unknown codes fall back to a plain line. */
export function reasonLine(
    status: string,
    reason: string | null,
    agent: string,
): string | null {
    const a = agent || 'the agent';
    const lines: Record<string, string> = {
        // Couldn't start (RD `couldnt_start_*`, hosted wording).
        baseline_rejected:
            'Your handler rejected a correctly signed delivery. Check the secret on the agent and the target path.',
        target_unreachable: `${a} couldn't connect to the target. Nothing else was sent. Is your app running?`,
        probe_unstable:
            "Your probe isn't stable: its value changed with no delivery. Remove time-based values such as updated_at or now().",
        reset_failed: `The reset failed on ${a} before the first delivery. Nothing was sent. The command and its output are in the agent's terminal.`,
        needs_secret: `This provider signs a timestamp, so nothing can be replayed without its signing secret on ${a}.`,
        agent_too_old: `${a} is too old for this run. Update it: npx @webhooktrap/agent@latest, npm i -g @webhooktrap/agent@latest, or rerun the install script.`,
        target_not_allowed: `${a} refused the job: this target isn't declared in its webhooktrap.yml.`,
        busy: `${a} was busy with another run. Run again when it's free.`,
        // Aborted (RD `aborted_*`).
        agent_lost: `${a} was lost during the run. Completed deliveries and findings are kept.`,
        target_lost:
            "Several deliveries in a row couldn't connect to the target. Completed deliveries and findings are kept.",
        time_limit:
            'The run reached the 15-minute limit. Completed deliveries and findings are kept.',
        user: 'Stopped in the terminal. Completed deliveries and findings are kept.',
        interrupted:
            'The agent was interrupted. Completed deliveries and findings are kept.',
        capture_verification_failed:
            'A capture failed its SHA-256 check on the agent, so the run stopped. Nothing unverified was sent.',
        internal_error:
            'The agent hit an internal error. Completed deliveries and findings are kept.',
        // Inconclusive.
        needs_probe:
            "Responses are identical, but we can't see your database. Add a probe to check for duplicate side effects.",
    };

    if (reason && lines[reason]) {
        return lines[reason];
    }

    if (status === 'inconclusive') {
        return lines.needs_probe;
    }

    // COPY PROPOSAL (A07): fallback when a reason code has no sentence yet.
    return reason ? `Reason: ${reason.replace(/_/g, ' ')}.` : null;
}

export const queuedCopy = {
    waiting: (agent: string) =>
        `Queued · Waiting for ${agent || 'your agent'} to accept the run…`,
    confirm: (agent: string) =>
        `Queued · Confirm in ${agent || 'the agent'}'s terminal: it shows the probe and reset commands from webhooktrap.yml and this target before it runs them.`,
    reconfirm: (agent: string) =>
        `Queued · Confirm in ${agent || 'the agent'}'s terminal: this session moved to your account, so it asks you to confirm the new workspace first.`,
};

/** `POST /p/{key}/runs` refusals (`errors.run`) and draft refusals. */
export const refusalCopy: Record<string, string> = {
    empty_selection: 'Select at least one delivery to prove.',
    cap: 'Too many events selected. A run takes up to 20 events.',
    unknown_capture:
        "Some selected deliveries aren't in this project any more. Pick them again from the stream.",
    provider_mix:
        'Select events from one provider. A run tests one provider at a time.',
    unknown_provider:
        "No provider pack matches these deliveries, so there's nothing to sign or judge them with.",
    body_not_kept:
        "A selected delivery has no stored body, and a run can't replay without it.",
    unknown_scenario: 'One of the scenarios is unknown. Reload the page.',
    fail_on: 'Pick when the run should fail: Critical, High, Medium or never.',
    server_runs_disabled:
        'Server runs are off on this instance. Pair an agent.',
    no_agent: 'Pair an agent first: runs go from your machine to your handler.',
    agent_needs_confirmation:
        'Confirm the agent in your terminal first: it waits for you to type y.',
    undeclared_target:
        "That target isn't declared by the agent. Add it to webhooktrap.yml, then restart the agent.",
    quota: 'The run limit for this session is reached. Sign in to keep running.',
};

export const statusWord: Record<string, string> = {
    queued: 'Queued',
    running: 'Running',
    couldnt_start: "Couldn't start",
    aborted: 'Aborted',
    findings: 'Findings',
    inconclusive: 'Inconclusive',
    passed: 'Passed',
};
