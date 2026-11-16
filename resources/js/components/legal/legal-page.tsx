import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LandingHeader } from '@/components/landing/landing-header';
import { LandingFooter } from '@/components/landing/sections';

/*
 * Public legal pages (privacy, terms): the landing's dark chrome (header and
 * footer) around one readable column. Public, no session state of their own.
 */

const wrap = 'mx-auto w-full max-w-[1280px] px-4 sm:px-7';

export function LegalPage({
    title,
    updated,
    lede,
    children,
}: {
    title: string;
    /** ISO date, shown as "Last updated". */
    updated: string;
    lede: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div className="landing min-h-screen" data-theme="dark">
                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:bg-lp-bg focus:px-3 focus:py-2 focus:text-sm focus:font-semibold"
                >
                    Skip to content
                </a>
                <LandingHeader />
                <main id="main" className={wrap}>
                    <article className="max-w-[72ch] pt-16 pb-20 sm:pt-20">
                        <p
                            className="text-[13px] text-lp-dim"
                            aria-hidden="true"
                        >
                            <span className="text-lp-mag">~/webhooktrap $</span>{' '}
                            cat {title.toLowerCase().replaceAll(' ', '-')}.md
                        </p>
                        <h1 className="mt-3 text-[clamp(30px,3.6vw,46px)] leading-[1.1] font-[750] tracking-[-0.03em] [font-stretch:80%]">
                            {title}
                        </h1>
                        <p className="mt-3 text-[13px] text-lp-muted">
                            Last updated{' '}
                            <time dateTime={updated}>{updated}</time>
                        </p>
                        <div className="mt-6 text-[15px] text-lp-muted">
                            {lede}
                        </div>
                        <div className="mt-4">{children}</div>
                    </article>
                </main>
                <LandingFooter />
            </div>
        </>
    );
}

/** One numbered section with an anchor. */
export function LegalSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className="mt-10 border-t border-lp-rule-soft pt-8 first:mt-6"
        >
            <h2
                id={id}
                className="text-[20px] leading-[1.25] font-[650] tracking-[-0.01em] text-lp-ink [font-stretch:87.5%]"
            >
                <a href={`#${id}`} className="text-inherit no-underline">
                    {title}
                </a>
            </h2>
            <div className="mt-4 flex flex-col gap-4 text-[14.5px] leading-[1.65] text-lp-muted [&_a]:text-lp-ink [&_a]:underline [&_a]:underline-offset-2 [&_h3]:mt-2 [&_h3]:text-[15px] [&_h3]:font-semibold [&_h3]:text-lp-ink [&_li]:pl-1 [&_strong]:font-semibold [&_strong]:text-lp-ink [&_ul]:flex [&_ul]:list-['–_'] [&_ul]:flex-col [&_ul]:gap-2 [&_ul]:pl-5">
                {children}
            </div>
        </section>
    );
}
