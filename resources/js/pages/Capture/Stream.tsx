import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import CaptureStream from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import {
    EscText,
    LiveIndicator,
    ProviderMark,
} from '@/components/capture/bits';
import type { DetailTab } from '@/components/capture/delivery-detail';
import { DeliveryDetail } from '@/components/capture/delivery-detail';
import { StreamList } from '@/components/capture/stream-list';
import { WaitingPanel } from '@/components/capture/waiting-panel';
import {
    GuestNextStep,
    GuestQuotaBanner,
} from '@/components/capture/guest-guide';
import { AppShell, CaptureUrlChip } from '@/components/shell/app-shell';
import { captureCopy, clock } from '@/lib/capture/format';
import type { CaptureRow, StreamProps, StreamQueryState } from '@/types';

/*
 * S-03 · Capture stream. The list (treegrid) and the S-04 pane for the
 * selection; below 1024 px the list alone, and a row opens the S-04 page.
 * Query state lives in the URL (`?sel=&tab=&view=&q=&group=`), so back
 * returns to the previous selection. `captures`, `groups`, `captureUrl`
 * and the guest meter are live props: Inertia reloads them on
 * `project.captures` / `project.capture_url` (ADR-004 am. 1); the
 * selection never moves when rows arrive.
 */

const WIDE = '(min-width: 1024px)';
const ANNOUNCE_EVERY_MS = 5000;

function queryFor(state: StreamQueryState): Record<string, string> {
    const out: Record<string, string> = {};

    if (state.sel !== null) {
        out.sel = String(state.sel).padStart(2, '0');
    }

    if (state.tab !== 'verdict') {
        out.tab = state.tab;
    }

    out.view = state.view;

    if (state.q) {
        out.q = state.q;
    }

    if (state.group) {
        out.group = state.group;
    }

    return out;
}

export default function Stream({
    project,
    captureUrl,
    query,
    captures,
    groups,
    selected,
    providers,
}: StreamProps) {
    const [older, setOlder] = useState<CaptureRow[]>([]);
    const [olderCursor, setOlderCursor] = useState<number | null | undefined>(
        undefined,
    );
    const rows = useMemo(() => {
        const seen = new Set<number>();

        return [...captures.latest, ...older].filter((row) =>
            seen.has(row.seq) ? false : (seen.add(row.seq), true),
        );
    }, [captures.latest, older]);

    // Arrivals since load: highlighted once, announced at most every 5 s.
    const known = useRef<Set<number> | null>(null);
    const [fresh, setFresh] = useState<ReadonlySet<number>>(new Set());
    const [announce, setAnnounce] = useState('');
    const pending = useRef(0);
    const lastAnnounce = useRef(0);
    const [newPill, setNewPill] = useState(0);
    const listRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (known.current === null) {
            known.current = new Set(captures.latest.map((r) => r.seq));

            return;
        }
        const arrived = captures.latest.filter(
            (r) => !known.current?.has(r.seq),
        );

        if (arrived.length === 0) {
            return;
        }
        arrived.forEach((r) => known.current?.add(r.seq));
        setFresh(new Set(arrived.map((r) => r.seq)));
        pending.current += arrived.length;

        if ((listRef.current?.scrollTop ?? 0) > 40) {
            setNewPill((n) => n + arrived.length);
        }
        const wait = Math.max(
            0,
            lastAnnounce.current + ANNOUNCE_EVERY_MS - Date.now(),
        );
        const t = window.setTimeout(() => {
            if (pending.current > 0) {
                setAnnounce(captureCopy.arrivals(pending.current));
                pending.current = 0;
                lastAnnounce.current = Date.now();
            }
        }, wait);

        return () => window.clearTimeout(t);
    }, [captures.latest]);

    const visit = useCallback(
        (patch: Partial<StreamQueryState>, partial = true) => {
            const url = CaptureStream.index.url(project.key, {
                query: queryFor({ ...query, ...patch }),
            });
            router.visit(url, {
                preserveState: true,
                preserveScroll: true,
                ...(partial ? { only: ['selected', 'query'] } : {}),
            });
        },
        [project.key, query],
    );

    const select = useCallback(
        (seq: number) => {
            if (!window.matchMedia(WIDE).matches) {
                router.visit(
                    CaptureStream.show.url(
                        { projectKey: project.key, n: seq },
                        query.tab !== 'verdict'
                            ? { query: { tab: query.tab } }
                            : undefined,
                    ),
                );

                return;
            }
            visit({ sel: seq });
        },
        [project.key, query.tab, visit],
    );

    const setTab = useCallback((tab: DetailTab) => visit({ tab }), [visit]);

    // Prev/next in the selection's group (ungrouped steps through ungrouped).
    const siblings = selected
        ? rows
              .filter((r) => r.groupKey === selected.groupKey)
              .map((r) => r.seq)
              .sort((a, b) => a - b)
        : [];
    const at = selected ? siblings.indexOf(selected.seq) : -1;
    const prev = at > 0 ? siblings[at - 1] : null;
    const next = at >= 0 && at < siblings.length - 1 ? siblings[at + 1] : null;
    const paneHref = (seq: number) =>
        CaptureStream.index.url(project.key, {
            query: queryFor({ ...query, sel: seq }),
        });

    // Keys (S-03): / filter · n newest · v grouped/flat · [ ] group nav.
    const filterRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                target?.closest('input, textarea, select, [role="menu"]')
            ) {
                if (event.key === 'Escape' && target === filterRef.current) {
                    listRef.current
                        ?.querySelector<HTMLElement>('[tabindex="0"]')
                        ?.focus();
                }

                return;
            }
            const keys: Record<string, () => void> = {
                '/': () => filterRef.current?.focus(),
                n: () => rows[0] && select(rows[0].seq),
                v: () =>
                    visit(
                        { view: query.view === 'grouped' ? 'flat' : 'grouped' },
                        false,
                    ),
                '[': () => prev !== null && select(prev),
                ']': () => next !== null && select(next),
                Escape: () =>
                    listRef.current
                        ?.querySelector<HTMLElement>('[tabindex="0"]')
                        ?.focus(),
            };
            const run = keys[event.key];

            if (run) {
                event.preventDefault();
                run();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [rows, select, visit, query.view, prev, next]);

    const filter = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const raw = new FormData(event.currentTarget).get('q');
        const q = (typeof raw === 'string' ? raw : '').trim().slice(0, 200);
        visit({ q: q || null, sel: null }, false);
    };

    const loadOlder = () => {
        const before = rows.at(-1)?.seq;

        if (!before) {
            return;
        }
        router.reload({
            only: ['captures.older'],
            data: { before },
            preserveUrl: true,
            onSuccess: (page) => {
                const got = (page.props as unknown as StreamProps).captures
                    .older;

                if (got) {
                    setOlder((o) => [...o, ...got.rows]);
                    setOlderCursor(got.nextBefore);
                }
            },
        });
    };
    const hasOlder =
        olderCursor === undefined
            ? captures.stats.hasOlder
            : olderCursor !== null;

    const waiting =
        captures.stats.total === 0 && !query.q && query.group === null;
    const groupFilterLabel =
        query.group === null
            ? ''
            : query.group === 'ungrouped'
              ? 'Ungrouped'
              : (groups?.find((g) => g.key === query.group)?.label ??
                rows.find((r) => r.groupKey === query.group)?.groupLabel ??
                query.group);
    const counts = `${captures.stats.total} ${captures.stats.total === 1 ? 'delivery' : 'deliveries'}${
        groups && query.view === 'grouped'
            ? ` · ${groups.length} ${groups.length === 1 ? 'group' : 'groups'}`
            : ''
    }${captures.stats.lastReceivedAt ? ` · last ${clock(captures.stats.lastReceivedAt)} UTC` : ''}`;

    return (
        <AppShell title={project.name} wide>
            <div className="tt-cap">
                <header className="tt-cap-head">
                    <h1>Arrivals</h1>
                    {!waiting && <LiveIndicator />}
                    {!waiting && <p className="tt-muted counts">{counts}</p>}
                    <span className="tt-spacer" />
                    {!waiting && (
                        <>
                            <div
                                className="tt-seg"
                                role="group"
                                aria-label="View"
                            >
                                {(['grouped', 'flat'] as const).map((v) => (
                                    <button
                                        key={v}
                                        type="button"
                                        aria-pressed={query.view === v}
                                        onClick={() =>
                                            visit({ view: v }, false)
                                        }
                                    >
                                        {v === 'grouped' ? 'Grouped' : 'Flat'}
                                    </button>
                                ))}
                            </div>
                            <form
                                role="search"
                                className="tt-filter"
                                onSubmit={filter}
                            >
                                <label htmlFor="cap-filter" className="tt-sr">
                                    Filter deliveries
                                </label>
                                <input
                                    ref={filterRef}
                                    id="cap-filter"
                                    name="q"
                                    type="search"
                                    className="tt-input"
                                    placeholder="Filter: event, provider, path, #n"
                                    defaultValue={query.q ?? ''}
                                    maxLength={200}
                                />
                            </form>
                        </>
                    )}
                    <span className="lg:hidden">
                        <CaptureUrlChip url={captureUrl} />
                    </span>
                </header>

                {!waiting && (
                    <div className="tt-cap-filters">
                        <div
                            className="tt-chips"
                            role="group"
                            aria-label="Provider"
                        >
                            <button
                                type="button"
                                aria-pressed={!query.q}
                                onClick={() =>
                                    visit({ q: null, sel: null }, false)
                                }
                            >
                                All
                            </button>
                            {providers
                                .filter((p) => p.id !== 'curl')
                                .map((p) => (
                                    <button
                                        key={p.id}
                                        type="button"
                                        aria-pressed={
                                            query.q?.toLowerCase() ===
                                            p.name.toLowerCase()
                                        }
                                        onClick={() =>
                                            visit(
                                                { q: p.name, sel: null },
                                                false,
                                            )
                                        }
                                    >
                                        <ProviderMark
                                            provider={p.id}
                                            name={p.name}
                                        />
                                    </button>
                                ))}
                        </div>
                        {query.group !== null && (
                            <button
                                type="button"
                                className="tt-chipclear"
                                onClick={() =>
                                    visit({ group: null, sel: null }, false)
                                }
                                aria-label={`Clear group filter ${groupFilterLabel}`}
                            >
                                Group:{' '}
                                <EscText text={groupFilterLabel} escaped />{' '}
                                <span aria-hidden="true">×</span>
                            </button>
                        )}
                    </div>
                )}

                <p className="tt-sr" aria-live="polite">
                    {announce}
                </p>

                <GuestQuotaBanner />
                {!waiting && <GuestNextStep />}

                {waiting ? (
                    <div className="tt-cap-waiting">
                        <WaitingPanel
                            captureUrl={captureUrl}
                            providers={providers}
                        />
                    </div>
                ) : (
                    <div className="tt-cap-body">
                        <div className="tt-cap-list" ref={listRef}>
                            {newPill > 0 && (
                                <button
                                    type="button"
                                    className="tt-newpill"
                                    onClick={() => {
                                        listRef.current?.scrollTo({ top: 0 });
                                        setNewPill(0);
                                    }}
                                >
                                    {captureCopy.newPill(newPill)}
                                </button>
                            )}
                            {rows.length === 0 && query.q ? (
                                <div className="tt-empty">
                                    <p className="tt-strong">
                                        {
                                            captureCopy.noResults(
                                                query.q,
                                                captures.stats.total,
                                            ).label
                                        }
                                    </p>
                                    <p className="tt-muted">
                                        {
                                            captureCopy.noResults(
                                                query.q,
                                                captures.stats.total,
                                            ).helper
                                        }
                                    </p>
                                    <button
                                        type="button"
                                        className="tt-btn tt-btn-sm"
                                        onClick={() =>
                                            visit({ q: null }, false)
                                        }
                                    >
                                        Clear filter
                                    </button>
                                </div>
                            ) : (
                                <StreamList
                                    rows={rows}
                                    view={query.view}
                                    groups={groups}
                                    selectedSeq={selected?.seq ?? null}
                                    fresh={fresh}
                                    onSelect={select}
                                />
                            )}
                            {hasOlder && (
                                <button
                                    type="button"
                                    className="tt-btn tt-btn-sm tt-older"
                                    onClick={loadOlder}
                                >
                                    {/* COPY PROPOSAL (A07) */}
                                    Load older deliveries
                                </button>
                            )}
                        </div>
                        <div className="tt-cap-pane">
                            {selected ? (
                                <DeliveryDetail
                                    capture={selected}
                                    projectKey={project.key}
                                    captureUrl={captureUrl}
                                    tab={query.tab}
                                    onTab={setTab}
                                    prev={prev}
                                    next={next}
                                    prevHref={paneHref}
                                    nextHref={paneHref}
                                />
                            ) : (
                                <p className="tt-muted tt-empty">
                                    {/* COPY PROPOSAL (A07) */}
                                    Select a delivery to see it here.
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppShell>
    );
}
