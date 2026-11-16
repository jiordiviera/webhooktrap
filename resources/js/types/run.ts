/** Run page props (contracts §3.2 `Run/Show`, §3.3; S-11, S-14). */

export type RunStatus =
    | 'queued'
    | 'running'
    | 'couldnt_start'
    | 'aborted'
    | 'findings'
    | 'inconclusive'
    | 'passed';

export type Severity = 'critical' | 'high' | 'medium' | 'info';

export type Provenance = {
    executor: 'agent' | 'ci' | 'server_public' | 'server_allowlist';
    source: 'web' | 'cli' | 'ci';
    agentName?: string;
    ciTokenName?: string;
    runner?: true;
};

export type RunHeader = {
    id: string;
    number: number;
    status: RunStatus;
    reason: string | null;
    exitCode: 0 | 1 | 2 | 3 | null;
    provenance: Provenance;
    agent?: { name: string; version: string } | null;
    provider: string;
    pack: { id: string; version: string };
    budget: { ms: number; source: string; label: string };
    target: { scheme: string; host: string; port: number; path: string };
    levels: (1 | 2)[];
    probeEntries: {
        name: string;
        displayMode: 'count' | 'code' | 'opted_number' | 'opted_value';
    }[];
    counts: Record<Severity | 'findings', number>;
    from: { groupKey?: string; seqs: number[] };
    startedAt: string | null;
    finishedAt: string | null;
    durationMs: number | null;
    notChecked: string[];
    orderDependent: boolean;
    visibility: 'private' | 'link';
    /** Owner run pages (agent connection notes). */
    queuedAt?: string | null;
    /**
     * Owner runs: planned deliveries ("D8 of 17"), from the job or the
     * agent's plan; null until known. The example run has none.
     */
    deliveryCount?: number | null;
    /** Example extras (S-14); `project`, `failOn`, `framework` also on owner runs. */
    title?: string;
    project?: string;
    framework?: string | null;
    failOn?: string;
    burstSpreadMs?: number;
};

export type ProbeReading = {
    name: string;
    changed: boolean | null;
    matchesExpected: boolean | null;
    flagged: boolean;
    display: {
        before: string | null;
        after: string | null;
        expected: string | null;
    };
};

export type RunDeliveryRow = {
    d: number;
    scenario: string;
    sourceSeq: number | null;
    mode: string;
    offsetMs: number;
    signing: { kind: string; timestampOffsetS?: number };
    tamper?: { path: string; from: string; to: string } | null;
    status: number | null;
    transport: string | null;
    latencyMs: number | null;
    overBudget: boolean | null;
    response?: { contentType: string | null; bytes: number; sha256: string };
    state?: { readings: ProbeReading[]; failed: boolean; unsettled: boolean };
    result:
        | 'pass'
        | 'finding'
        | 'inconclusive'
        | 'unreachable'
        | 'sent_no_result'
        | 'not_run'
        | null;
    /** Example extras: G2 inspector fields. */
    eventType?: string | null;
    /** Display name of `scenario`, added on the client. */
    scenarioName?: string;
    finding?: number | null;
    budgetJudged?: boolean;
    inspector?: {
        sentAt: string;
        completedMs: number;
        request: {
            method: string;
            url: string;
            headers: [string, string][];
            bodyNote: string | null;
            sizeBytes: number | null;
        };
        response: { headers: [string, string][]; body: string };
    };
};

export type FindingView = {
    n: number;
    /** True while Running: no F-number yet (FND-6); `n` is the display order. */
    provisional?: boolean;
    scenarioKeys?: string[];
    type: string;
    severity: Severity;
    level: 1 | 2;
    title: string;
    eventType: string | null;
    scenarios: string[];
    evidence: { d: number; role: 'offending' | 'reference' }[];
    what: string;
    why: string;
    citation: { label: string; url: string } | null;
    fix: { text: string; framework: string | null; snippet: string | null };
    likelyCause: { rule: string; text: string } | null;
    fingerprint: string;
    seenBefore: boolean;
    levelLabel?: string;
    caption?: string | null;
    measured?: string | null;
};

/** S-11 strip cell (contracts §3.2 lists `ScenarioCell[]`). */
export type ScenarioCell = {
    key: string;
    name: string;
    result:
        | 'pass'
        | 'finding'
        | 'inconclusive'
        | 'unreachable'
        | 'n/a'
        | 'not_run'
        | null;
    reason?: string | null;
    deliveries: number[];
    findings: { n: number; severity: Severity }[];
    counted: number;
    reading: string;
    line: string;
};

export type RunFocus = {
    kind: 'delivery' | 'finding';
    n: number;
    missing: boolean;
} | null;

export type RunShowProps = {
    run: RunHeader;
    scenarios: ScenarioCell[];
    timeline: {
        lastSeq: number;
        status: RunStatus;
        deliveries: RunDeliveryRow[];
    };
    findings: FindingView[];
    focus: RunFocus;
    access: 'owner' | 'shared' | 'example';
    example?: { label: string; helper: string };
    sharedBy?: string;
    /** Owner: share state (Sharing slice fills it). */
    share?: ShareState;
    /** Owner, while not final: subscribe to `private-{channel}` for timeline events. */
    live?: { channel: string } | null;
    /** Owner only (users and guests): "Runs" and "Run again" links. */
    project?: { key: string; name: string; isGuest: boolean } | null;
};

export type ShareState = {
    visibility: 'private' | 'link';
    shareUrl?: string;
    sharedBy: string;
    bodiesHidden: boolean;
    includeProbeValues: boolean;
};

/** S-05 `Runs/Index` row (`runs` is live on `project.runs`). */
export type RunRow = {
    id: string;
    number: number;
    status: RunStatus;
    reason: string | null;
    source: 'web' | 'cli' | 'ci';
    provenance: Provenance;
    provider: string;
    providerName: string | null;
    target: { scheme: string; host: string; port: number; path: string };
    counts: Record<Severity | 'findings', number>;
    from: { seqs: number[] };
    queuedAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
};

export type RunsIndexProps = {
    project: { key: string; name: string; isGuest: boolean };
    runs: RunRow[];
    filters: { source: 'web' | 'cli' | 'ci' | null; status: RunStatus | null };
};

export type AgentSummary = {
    id: string;
    name: string;
    os: string;
    keyFingerprint: string;
    state:
        | 'awaiting_confirmation'
        | 'connected'
        | 'disconnected'
        | 'needs_reconfirm';
    version: string;
};

export type RunTarget = {
    kind: 'agent';
    agentId: string;
    /** Origin of a declared target; the run's `targetRef` may add a path. */
    targetRef: string;
    display: string;
    declared: true;
    approvedOnAgent: boolean;
};

export type CaptureRef = {
    seq: number;
    id: string;
    provider: string | null;
    providerName: string | null;
    eventType: string | null;
    receivedAt: string;
    bodySha256: string;
    bodyKept: boolean;
    bodySize: number;
    signature: string;
};

/** Codes that refuse `POST /p/{projectKey}/runs` (422, `errors.run`). */
export type RunRefusalCode =
    | 'empty_selection'
    | 'cap'
    | 'unknown_capture'
    | 'provider_mix'
    | 'unknown_provider'
    | 'body_not_kept'
    | 'unknown_scenario'
    | 'fail_on'
    | 'server_runs_disabled'
    | 'no_agent'
    | 'agent_needs_confirmation'
    | 'undeclared_target'
    | 'quota';

/** S-10 `Runs/Draft`. `preview` stays null until the server can plan (EngineProcess). */
export type RunsDraftProps = {
    project: { key: string; name: string; isGuest: boolean };
    selection: CaptureRef[];
    preview: null;
    refusals: RunRefusalCode[];
    targets: RunTarget[];
    agent: AgentSummary | null;
    agents: AgentSummary[];
    /** Absent: the page asks `POST /pairing-codes` for a code when it needs one. */
    pairing: PairingCodeView | null;
    scenarios: { key: string; name: string }[];
    failOn: 'critical' | 'high' | 'medium' | 'never';
};

export type PairingCodeView = {
    code: string;
    expiresAt: string;
    /** `npx @webhooktrap/agent@1 pair <CODE> --server <origin>` (AC-14, ADR-010). */
    command: string;
    /**
     * AC-3: set when the owner asked `POST /pairing-codes {replace: agentId}`.
     * The agent paired with this code takes this name and revokes this agent
     * at its confirmation; show both key fingerprints. Null: nothing is
     * replaced, a colliding name gets a suffix (`maya-mbp-2`).
     */
    replaces: { agentId: string; name: string; keyFingerprint: string } | null;
};

/** Shared prop (contracts §3.1): the principal's workspace agent for the shell pill; null when anonymous. */
export type AgentPill = {
    state:
        | 'none'
        | 'awaiting_confirmation'
        | 'connected'
        | 'disconnected'
        | 'too_old';
    name?: string;
    version?: string;
};

/** `/agents` row (live on `private-workspace.{id}`, event `workspace.agents`). */
export type AgentRow = AgentSummary & {
    tooOld: boolean;
    pairedAt: string;
    lastSeenAt: string | null;
    /** `DELETE` here revokes (owner, user or guest; JSON 204, Inertia 303 back with flash `agent_revoked`). */
    revokeUrl: string;
};

/** `/agents/{idOrName}` and the guest agent page: mirrors are display-only, secret names never values. */
export type AgentDetail = AgentRow & {
    arch: string | null;
    targets: {
        scheme: string;
        host: string;
        port: number;
        kind: 'local' | 'allowed';
        declared: boolean;
        approved: boolean;
    }[];
    secretNames: { provider: string; envName: string }[];
};

/** `GET /agents` → `Agents/Index`. `pairing` is null: the page calls `POST /pairing-codes`. */
export type AgentsIndexProps = { agents: AgentRow[]; pairing: null };

/** `GET /agents/pair[?replace=agt_…]` → `Agents/Pair`; `replace` is a live agent id or null. */
export type AgentsPairProps = {
    agents: AgentRow[];
    pairing: null;
    replace: string | null;
};

/** `GET /agents/{idOrName}` → `Agents/Show`. */
export type AgentsShowProps = { agent: AgentDetail };

/** `GET /p/{projectKey}/agent` (guest owner) → `Agent/Guest`; `agent` live. */
export type AgentGuestProps = {
    project: { key: string; name: string; isGuest: true };
    agent: AgentDetail | null;
    pairing: null;
};
