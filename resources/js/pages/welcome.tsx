import { Head, usePage } from '@inertiajs/react';
import { useEffect, useEffectEvent, useRef } from 'react';
import { Hero } from '@/components/landing/hero';
import { LandingHeader } from '@/components/landing/landing-header';
import {
    CodingAgent,
    FailingTests,
    FooterCta,
    LandingFooter,
    Providers,
    RealTraffic,
    Scenarios,
    Secrets,
} from '@/components/landing/sections';
import { StickyCta } from '@/components/landing/sticky-cta';
import { Toaster } from '@/components/ui/sonner';
import { flashCopy } from '@/lib/identity/copy';
import { notify } from '@/lib/notify';
import type { Flash } from '@/types/identity';

/** The landing's own toaster: it renders inside the dark landing root. */
const LANDING_TOASTER = 'landing';

/**
 * S-01 Landing, decision 57 "terminal" direction (A04 mockup
 * project/design/directions/direction-2-terminal.html). Dark only.
 */
export default function Welcome() {
    const heroRef = useRef<HTMLDivElement>(null);
    const footerRef = useRef<HTMLElement>(null);
    const { flash } = usePage().props;

    // Flashes that land here: "Session ended. Nothing was kept.", "Email
    // confirmed." (S-16, S-18, S-25; F-04).
    const announce = useEffectEvent((current: Flash) => {
        const text = flashCopy(current);

        if (text) {
            notify.flash(current, text, LANDING_TOASTER);
        }
    });

    useEffect(() => {
        if (flash) {
            announce(flash);
        }
    }, [flash]);

    return (
        <>
            <Head title="Your webhook handler works. Once." />
            <div className="landing min-h-screen" data-theme="dark">
                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-20 focus:bg-lp-bg focus:px-3 focus:py-2 focus:text-sm focus:font-semibold"
                >
                    Skip to content
                </a>
                <LandingHeader />
                <main id="main">
                    <div ref={heroRef}>
                        <Hero />
                    </div>
                    <FailingTests />
                    <Scenarios />
                    <RealTraffic />
                    <Secrets />
                    <CodingAgent />
                    <Providers />
                    <FooterCta ref={footerRef} />
                </main>
                <LandingFooter />
                <StickyCta heroRef={heroRef} footerRef={footerRef} />
                <Toaster id={LANDING_TOASTER} theme="dark" />
            </div>
        </>
    );
}
