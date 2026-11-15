import { Link } from '@inertiajs/react';
import ExampleRun from '@/actions/App/Modules/Proving/Http/Controllers/ExampleRunController';
import { CopyCommand, GetUrlButton } from './landing-button';
import { RunTerminal } from './run-terminal';

export function Hero() {
    return (
        <section
            aria-labelledby="lp-h1"
            className="mx-auto grid w-full max-w-[1280px] grid-cols-1 items-start gap-8 px-4 pt-9 pb-12 min-[641px]:pt-16 min-[641px]:pb-[72px] min-[1001px]:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] min-[1001px]:gap-14 sm:px-7"
        >
            <div className="min-w-0">
                <h1
                    id="lp-h1"
                    className="text-[clamp(38px,4.6vw,62px)] leading-[1.08] font-[750] tracking-[-0.035em] [font-stretch:80%]"
                >
                    Your webhook handler works. Once.
                    <span className="lp-caret" aria-hidden="true" />
                </h1>
                {/* COPY PROPOSAL (A04, decision 57 mockup): subline, not in the RD yet. */}
                <p className="mt-7 mb-8 max-w-[46ch] text-[15.5px] leading-[1.7] text-lp-muted">
                    Stripe delivers twice, out of order, late, and again after a
                    timeout. Webhook Trap replays your real events that way
                    against your handler and fails the build when your database
                    ends up wrong.
                </p>
                <CopyCommand className="mb-3.5" />
                <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
                    <GetUrlButton className="max-sm:w-full" />
                    <Link
                        href={ExampleRun.show.url()}
                        className="text-[14px] font-semibold text-lp-ink underline decoration-lp-dim underline-offset-4 hover:decoration-lp-mag"
                    >
                        See an example run ›
                    </Link>
                </div>
                <p className="mt-[22px] text-[12.5px] text-lp-dim">
                    Open source · self-hostable · secrets stay on your machine
                </p>
            </div>
            <div className="min-w-0">
                <RunTerminal />
            </div>
        </section>
    );
}
