import { router } from '@inertiajs/react';
import {
    Check,
    Info,
    LoaderCircle,
    OctagonX,
    TriangleAlert,
    Undo2,
    X,
    type LucideIcon,
} from 'lucide-react';
import { toast, type ExternalToast } from 'sonner';
import type { Flash } from '@/types/identity';

/*
 * Toasts (Claude Design canvas "Webhook Trap — Toasts"): a ruled card, the
 * kind's colour on a 2 px timer line along the top and on the icon chip,
 * technical details in Martian Mono. Errors stay until dismissed and are
 * announced at once (role="alert"); everything else is a polite status.
 */

export type ToastKind =
    | 'success'
    | 'info'
    | 'warning'
    | 'error'
    | 'undo'
    | 'brief';

export type ToastOptions = {
    title: string;
    description?: string | null;
    /** One technical line in mono: an id, a command, a count. */
    meta?: string | null;
    action?: { label: string; href?: string; onClick?: () => void } | null;
    /** In ms; Infinity stays until dismissed. Defaults per kind. */
    duration?: number;
    id?: string | number;
    /** A page with its own toaster (the landing). */
    toasterId?: string;
};

const DURATIONS: Record<ToastKind, number> = {
    success: 6000,
    info: 6000,
    warning: 8000,
    error: Number.POSITIVE_INFINITY,
    undo: 7000,
    brief: 2500,
};

const ICONS: Record<ToastKind, LucideIcon> = {
    success: Check,
    info: Info,
    warning: TriangleAlert,
    error: OctagonX,
    undo: Undo2,
    brief: Check,
};

function WtToast({
    id,
    kind,
    options,
    loading,
}: {
    id: string | number;
    kind: ToastKind;
    options: ToastOptions;
    loading: boolean;
}) {
    const Icon = loading ? LoaderCircle : ICONS[kind];
    const duration = options.duration ?? DURATIONS[kind];
    const timed = !loading && Number.isFinite(duration);
    const action = loading ? null : options.action;

    const act = () => {
        toast.dismiss(id);
        action?.onClick?.();

        if (action?.href) {
            router.visit(action.href);
        }
    };

    if (kind === 'brief') {
        return (
            <div role="status" className="wt-toast" data-kind="brief">
                {timed && (
                    <span
                        aria-hidden="true"
                        className="wt-toast-bar"
                        style={{ animationDuration: `${duration}ms` }}
                    />
                )}
                <Icon className="wt-toast-tick" aria-hidden="true" />
                <p className="wt-toast-title">{options.title}</p>
                {options.meta && (
                    <p className="wt-toast-meta">{options.meta}</p>
                )}
            </div>
        );
    }

    return (
        <div
            role={kind === 'error' ? 'alert' : 'status'}
            aria-busy={loading || undefined}
            className="wt-toast"
            data-kind={kind}
            data-loading={loading || undefined}
        >
            {(timed || loading || kind === 'error') && (
                <span
                    aria-hidden="true"
                    className="wt-toast-bar"
                    style={
                        timed
                            ? { animationDuration: `${duration}ms` }
                            : undefined
                    }
                />
            )}
            <span className="wt-toast-chip" aria-hidden="true">
                <Icon />
            </span>
            <div className="wt-toast-body">
                <p className="wt-toast-title">{options.title}</p>
                {options.description && (
                    <p className="wt-toast-desc">{options.description}</p>
                )}
                {options.meta && (
                    <p className="wt-toast-meta">{options.meta}</p>
                )}
                {action && kind !== 'undo' && (
                    <div className="wt-toast-acts">
                        <button
                            type="button"
                            className="wt-toast-btn"
                            onClick={act}
                        >
                            {action.label}
                        </button>
                    </div>
                )}
            </div>
            {action && kind === 'undo' && (
                <button
                    type="button"
                    className="wt-toast-btn"
                    data-strong
                    onClick={act}
                >
                    {action.label}
                </button>
            )}
            {!loading && (
                <button
                    type="button"
                    className="wt-toast-x"
                    aria-label="Dismiss"
                    onClick={() => toast.dismiss(id)}
                >
                    <X aria-hidden="true" />
                </button>
            )}
        </div>
    );
}

function show(
    kind: ToastKind,
    options: ToastOptions,
    loading = false,
): string | number {
    const external: ExternalToast = {
        duration: loading
            ? Number.POSITIVE_INFINITY
            : (options.duration ?? DURATIONS[kind]),
        id: options.id,
        toasterId: options.toasterId,
    };

    return toast.custom(
        (id) => (
            <WtToast id={id} kind={kind} options={options} loading={loading} />
        ),
        external,
    );
}

type Input = ToastOptions | string;

const opts = (input: Input): ToastOptions =>
    typeof input === 'string' ? { title: input } : input;

export const notify = {
    success: (o: Input) => show('success', opts(o)),
    info: (o: Input) => show('info', opts(o)),
    warning: (o: Input) => show('warning', opts(o)),
    error: (o: Input) => show('error', opts(o)),
    undo: (o: Input) => show('undo', opts(o)),
    /** A one-line confirmation that passes quickly ("Copied"). */
    brief: (o: Input) => show('brief', opts(o)),
    dismiss: (id?: string | number) => toast.dismiss(id),

    /** A long action updates in place: loading, then success or the error. */
    promise<T>(
        work: Promise<T>,
        steps: {
            loading: ToastOptions;
            success: (value: T) => ToastOptions;
            error: (reason: unknown) => ToastOptions;
        },
    ): Promise<T> {
        const id = show('info', steps.loading, true);

        work.then(
            (value) => show('success', { ...steps.success(value), id }),
            (reason: unknown) => show('error', { ...steps.error(reason), id }),
        );

        return work;
    },

    /** A server flash, in the tone of its kind, with the page's copy. */
    flash(flash: Flash, text: string, toasterId?: string) {
        const kind: ToastKind = flash.kind in DURATIONS ? flash.kind : 'info';

        return show(kind, { title: text, toasterId });
    },
};
