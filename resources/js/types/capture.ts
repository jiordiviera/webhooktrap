/** Capture stream and delivery detail props (A05 contracts §3.2, §3.3; slice 4 notes). */

export type SignatureState =
    | 'unsigned'
    | 'unknown_scheme'
    | 'invalid'
    | 'not_checked'
    | 'no_secret'
    | 'valid'
    | 'checking';

export type SignatureChip = {
    state: SignatureState;
    reason?: string;
    checksPassed?: number;
    checksTotal?: number;
};

/**
 * `method`, `path`, `eventType`, `providerName` and `groupLabel` are display
 * text: control, bidi and format characters arrive escaped as `\u{XXXX}`
 * (A09 S3-16). Render as text only. `provider` is a pack id
 * (`stripe`, `github`, `shopify`, `standard_webhooks`) or null (Unknown);
 * `providerName` names the sender (`Parcelbird` on Standard Webhooks).
 * `groupKey` is an opaque filter value (`?group=`), `groupLabel` the GRP-3
 * label (`Order #1042`, `sub_1QH3UF1HXb5oPQT3wfdt4drI`).
 */
export type CaptureRow = {
    seq: number;
    receivedAt: string;
    method: string;
    path: string;
    provider: string | null;
    providerName: string | null;
    eventType: string | null;
    groupKey: string | null;
    groupLabel: string | null;
    signature: SignatureChip;
    bodySize: number;
    bodyKept: boolean;
    shapeVerdict: 'new' | 'matches' | 'differs' | null;
};

export type InspectorBody = {
    encoding: 'utf8' | 'base64';
    text: string;
    truncated: boolean;
    shownBytes: number;
    escaped: boolean;
};

/** Derived facts for the Decoded tab (packs slice); absent until the capture is derived. */
export type DecodedFacts = {
    provider: string | null;
    providerName: string | null;
    eventType: string | null;
    providerEventId: string | null;
    apiVersion: string | null;
    objectIds: string[];
    group: { key: string; label: string; kind: string | null } | null;
    /** `stripe@1.0.0`, or `none@<pack set version>` when no pack matched. */
    pack: string;
};

export type CaptureDetail = CaptureRow & {
    id: string;
    rawTarget: string;
    rawQuery: string | null;
    headers: [string, string][];
    redactedHeaders: string[];
    headerFidelity: 'exact' | 'sapi';
    publicOrigin: string;
    publicOriginSource: 'configured' | 'forwarded_trusted' | 'direct';
    sourceIp: string;
    contentType: string | null;
    bodySha256: string;
    body?: InspectorBody;
    hiddenCharacters: boolean;
    signatureChecks?: { check: string; passed: boolean }[];
    decoded?: DecodedFacts;
    /** SHP-1 (path, JSON type) pairs, sorted; JSON bodies only. */
    shape?: [string, string][];
};

export type GroupSummary = {
    key: string | null;
    /** GRP-3 label (`Order #1042`); null for Ungrouped. */
    label: string | null;
    /** Anchor object noun (`Subscription`, `Order`, `Repository`, `Shipment`). */
    kind: string | null;
    provider: string | null;
    providerName: string | null;
    count: number;
    firstSeq: number;
    lastSeq: number;
    firstAt: string;
    lastAt: string;
    worstSignature: SignatureState;
};

export type StreamStats = {
    total: number;
    matching: number;
    lastReceivedAt: string | null;
    hasOlder: boolean;
};

export type StreamQueryState = {
    sel: number | null;
    tab: 'verdict' | 'raw' | 'decoded';
    view: 'grouped' | 'flat';
    q: string | null;
    group: string | null;
};

export type StreamProps = {
    project: { key: string; name: string; isGuest: boolean };
    captureUrl: string | null;
    query: StreamQueryState;
    captures: {
        latest: CaptureRow[];
        stats: StreamStats;
        older?: { rows: CaptureRow[]; nextBefore: number | null };
    };
    groups?: GroupSummary[];
    selected: CaptureDetail | null;
    providers: { id: string; name: string }[];
};

export type DetailProps = {
    project: { key: string; name: string; isGuest: boolean };
    capture: CaptureDetail;
    prev: number | null;
    next: number | null;
    tab: 'verdict' | 'raw' | 'decoded';
};
