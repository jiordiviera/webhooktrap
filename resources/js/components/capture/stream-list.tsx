import type { KeyboardEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    bytes,
    clock,
    clockMs,
    rowTitle,
    seqLabel,
} from '@/lib/capture/format';
import type { CaptureRow, GroupSummary } from '@/types/capture';
import { EscText, providerName, ProviderMark, SignatureWord } from './bits';

/*
 * S-03 arrivals list: a treegrid (A11Y §5.3) with one tab stop and a roving
 * tabindex. Grouped view: group rows (level 1) then their deliveries, oldest
 * first (level 2); flat view: deliveries newest first. New rows never steal
 * focus or move the selection.
 */

type Item =
    | {
          kind: 'group';
          id: string;
          key: string | null;
          label: string | null;
          groupKind: string | null;
          provider: string | null;
          providerName: string | null;
          count: number;
          lastAt: string;
      }
    | { kind: 'row'; id: string; row: CaptureRow; level: 1 | 2 };

const groupTitle = (label: string | null, key: string | null) =>
    label ?? (key ? key : 'Ungrouped');

function buildItems(
    rows: CaptureRow[],
    view: 'grouped' | 'flat',
    groups: GroupSummary[] | undefined,
    collapsed: ReadonlySet<string>,
): Item[] {
    if (view === 'flat') {
        return rows.map((row) => ({
            kind: 'row',
            id: `r${row.seq}`,
            row,
            level: 1,
        }));
    }
    const byGroup = new Map<string, CaptureRow[]>();

    for (const row of rows) {
        const k = row.groupKey ?? '';
        byGroup.set(k, [...(byGroup.get(k) ?? []), row]);
    }
    // Most recent activity first: the server's order when it sent groups,
    // otherwise the order rows arrive in (newest first).
    const order = groups?.map((g) => g.key ?? '') ?? [...byGroup.keys()];
    const items: Item[] = [];

    for (const k of order) {
        const members = byGroup.get(k);

        if (!members) {
            continue;
        }
        const summary = groups?.find((g) => (g.key ?? '') === k);
        items.push({
            kind: 'group',
            id: `g${k}`,
            key: k || null,
            label: summary?.label ?? members[0].groupLabel,
            groupKind: summary?.kind ?? null,
            // Ungrouped mixes senders: no provider on its header.
            provider: k
                ? summary
                    ? summary.provider
                    : members[0].provider
                : null,
            providerName: k
                ? summary
                    ? summary.providerName
                    : members[0].providerName
                : null,
            count: summary?.count ?? members.length,
            lastAt: summary?.lastAt ?? members[0].receivedAt,
        });

        if (!collapsed.has(k)) {
            for (const row of [...members].reverse()) {
                items.push({ kind: 'row', id: `r${row.seq}`, row, level: 2 });
            }
        }
    }

    return items;
}

export function StreamList({
    rows,
    view,
    groups,
    selectedSeq,
    fresh,
    onSelect,
}: {
    rows: CaptureRow[];
    view: 'grouped' | 'flat';
    groups?: GroupSummary[];
    selectedSeq: number | null;
    /** Seqs that arrived since the page loaded (highlighted once). */
    fresh: ReadonlySet<number>;
    onSelect: (seq: number) => void;
}) {
    const [collapsed, setCollapsed] = useState<ReadonlySet<string>>(new Set());
    const items = useMemo(
        () => buildItems(rows, view, groups, collapsed),
        [rows, view, groups, collapsed],
    );
    const [focusId, setFocusId] = useState<string | null>(null);
    const refs = useRef(new Map<string, HTMLDivElement>());
    const selectedId = selectedSeq !== null ? `r${selectedSeq}` : null;
    // The tab stop: the focused item if still shown, else the selection,
    // else the first item.
    const stop =
        (focusId && items.some((i) => i.id === focusId) && focusId) ||
        (selectedId && items.some((i) => i.id === selectedId) && selectedId) ||
        items[0]?.id;

    useEffect(() => {
        if (focusId && document.activeElement?.closest('[role="treegrid"]')) {
            refs.current.get(focusId)?.focus();
        }
    }, [focusId]);

    const toggle = (key: string | null) =>
        setCollapsed((prev) => {
            const next = new Set(prev);
            const k = key ?? '';

            if (next.has(k)) {
                next.delete(k);
            } else {
                next.add(k);
            }

            return next;
        });

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const index = items.findIndex((i) => i.id === stop);
        const item = items[index];

        if (!item) {
            return;
        }
        const move = (to: number) => {
            const target = items[Math.max(0, Math.min(items.length - 1, to))];

            if (target) {
                setFocusId(target.id);
            }
        };

        switch (event.key) {
            case 'ArrowDown':
            case 'j':
                move(index + 1);
                break;
            case 'ArrowUp':
            case 'k':
                move(index - 1);
                break;
            case 'Home':
                move(0);
                break;
            case 'End':
                move(items.length - 1);
                break;
            case 'ArrowRight':
                if (item.kind === 'group' && collapsed.has(item.key ?? '')) {
                    toggle(item.key);
                } else {
                    return;
                }
                break;
            case 'ArrowLeft':
                if (item.kind === 'group' && !collapsed.has(item.key ?? '')) {
                    toggle(item.key);
                } else if (item.kind === 'row' && item.level === 2) {
                    const parent = items
                        .slice(0, index)
                        .reverse()
                        .find((i) => i.kind === 'group');

                    if (parent) {
                        setFocusId(parent.id);
                    }
                } else {
                    return;
                }
                break;
            case 'Enter':
            case ' ':
                if (item.kind === 'group') {
                    toggle(item.key);
                } else {
                    onSelect(item.row.seq);
                }
                break;
            default:
                return;
        }
        event.preventDefault();
    };

    return (
        <div
            role="treegrid"
            aria-label="Deliveries"
            aria-readonly="true"
            className="tt-grid"
            onKeyDown={onKeyDown}
        >
            {items.map((item) => {
                const common = {
                    ref: (el: HTMLDivElement | null) => {
                        if (el) {
                            refs.current.set(item.id, el);
                        } else {
                            refs.current.delete(item.id);
                        }
                    },
                    tabIndex: item.id === stop ? 0 : -1,
                    onFocus: () => setFocusId(item.id),
                };

                if (item.kind === 'group') {
                    const open = !collapsed.has(item.key ?? '');

                    return (
                        <div
                            key={item.id}
                            role="row"
                            aria-level={1}
                            aria-expanded={open}
                            aria-label={`${groupTitle(item.label, item.key)}${item.groupKind ? `, ${item.groupKind}` : ''}${item.key ? `, ${item.providerName ?? providerName(item.provider)}` : ''}, ${item.count} ${item.count === 1 ? 'delivery' : 'deliveries'}, last ${clockMs(item.lastAt)} UTC`}
                            className="tt-grow"
                            onClick={() => toggle(item.key)}
                            {...common}
                        >
                            <span
                                role="gridcell"
                                className="chev"
                                aria-hidden="true"
                            >
                                {open ? '▾' : '▸'}
                            </span>
                            <span role="gridcell" className="gname">
                                <span className="lbl">
                                    <EscText
                                        text={groupTitle(item.label, item.key)}
                                        escaped
                                    />
                                </span>
                                {item.groupKind &&
                                    !groupTitle(
                                        item.label,
                                        item.key,
                                    ).startsWith(item.groupKind) && (
                                        <span className="tt-tag">
                                            {item.groupKind}
                                        </span>
                                    )}
                            </span>
                            <span role="gridcell" className="gprov">
                                {item.key && (
                                    <ProviderMark
                                        provider={item.provider}
                                        name={item.providerName}
                                    />
                                )}
                            </span>
                            <span role="gridcell" className="gcount tt-mono">
                                {item.count} ·{' '}
                                <span className="tt-muted">
                                    {clock(item.lastAt)}
                                </span>
                            </span>
                        </div>
                    );
                }
                const { row } = item;
                const selected = row.seq === selectedSeq;

                return (
                    <div
                        key={item.id}
                        role="row"
                        aria-level={item.level}
                        aria-selected={selected}
                        className={`tt-drow${fresh.has(row.seq) ? ' tt-highlight' : ''}`}
                        data-level={item.level}
                        onClick={() => onSelect(row.seq)}
                        {...common}
                    >
                        <span role="gridcell" className="seq tt-mono">
                            {seqLabel(row.seq)}
                        </span>
                        <span role="gridcell" className="time tt-mono">
                            {clockMs(row.receivedAt)}
                        </span>
                        <span role="gridcell" className="prov">
                            <ProviderMark
                                provider={row.provider}
                                name={row.providerName}
                                showName={false}
                            />
                        </span>
                        <span role="gridcell" className="ev">
                            <span className="pn">
                                <EscText
                                    text={
                                        row.providerName ??
                                        providerName(row.provider)
                                    }
                                    escaped
                                />
                            </span>
                            {' · '}
                            <EscText text={rowTitle(row)} escaped />
                        </span>
                        <span role="gridcell" className="size tt-mono">
                            {row.bodyKept
                                ? bytes(row.bodySize)
                                : 'body not kept'}
                        </span>
                        <span role="gridcell" className="sig">
                            <SignatureWord signature={row.signature} />
                        </span>
                    </div>
                );
            })}
        </div>
    );
}
