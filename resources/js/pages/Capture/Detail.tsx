import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';
import CaptureStream from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import type { DetailTab } from '@/components/capture/delivery-detail';
import { DeliveryDetail } from '@/components/capture/delivery-detail';
import { AppShell } from '@/components/shell/app-shell';
import { seqLabel } from '@/lib/capture/format';
import type { DetailProps } from '@/types';

/*
 * S-04 · Delivery detail as a page (`/p/{projectKey}/d/{n}`): mobile, deep
 * links and no-JS fallbacks. The heading takes focus on load; `Esc` goes
 * back to the stream with this delivery selected.
 */
export default function Detail({
    project,
    capture,
    prev,
    next,
    tab,
}: DetailProps) {
    const page = (n: number, t: DetailTab = tab) =>
        CaptureStream.show.url(
            { projectKey: project.key, n },
            t !== 'verdict' ? { query: { tab: t } } : undefined,
        );
    const streamHref = CaptureStream.index.url(project.key, {
        query: { sel: String(capture.seq).padStart(2, '0') },
    });

    const setTab = useCallback(
        (t: DetailTab) =>
            router.visit(page(capture.seq, t), {
                preserveState: true,
                preserveScroll: true,
                only: ['tab'],
            }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [capture.seq, project.key],
    );

    useEffect(() => {
        document.getElementById(`dlv-${capture.seq}-title`)?.focus();
    }, [capture.seq]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (target?.closest('input, textarea, select, [role="menu"]')) {
                return;
            }

            if (event.key === 'Escape') {
                router.visit(streamHref);
            } else if (event.key === '[' && prev !== null) {
                router.visit(page(prev));
            } else if (event.key === ']' && next !== null) {
                router.visit(page(next));
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    return (
        <AppShell title={`${seqLabel(capture.seq)} · ${project.name}`}>
            <div className="tt-page">
                <p className="tt-crumb">
                    <Link href={streamHref}>‹ Arrivals</Link>
                </p>
                <DeliveryDetail
                    capture={capture}
                    projectKey={project.key}
                    tab={tab}
                    onTab={setTab}
                    prev={prev}
                    next={next}
                    prevHref={(n) => page(n)}
                    nextHref={(n) => page(n)}
                    headingLevel={1}
                />
            </div>
        </AppShell>
    );
}
