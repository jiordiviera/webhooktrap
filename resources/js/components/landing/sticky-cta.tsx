import { useEffect, useState, type RefObject } from 'react';
import { GetUrlButton } from './landing-button';

/**
 * Phone only (< sm): a 52 px bar that appears once the hero CTA has scrolled
 * out and hides again near the footer CTA.
 */
export function StickyCta({
    heroRef,
    footerRef,
}: {
    heroRef: RefObject<HTMLElement | null>;
    footerRef: RefObject<HTMLElement | null>;
}) {
    const [heroGone, setHeroGone] = useState(false);
    const [footerNear, setFooterNear] = useState(false);

    useEffect(() => {
        if (!heroRef.current || !footerRef.current) return;
        const hero = new IntersectionObserver(([e]) =>
            setHeroGone(!e.isIntersecting && e.boundingClientRect.top < 0),
        );
        const footer = new IntersectionObserver(([e]) =>
            setFooterNear(e.isIntersecting),
        );
        hero.observe(heroRef.current);
        footer.observe(footerRef.current);
        return () => {
            hero.disconnect();
            footer.disconnect();
        };
    }, [heroRef, footerRef]);

    const visible = heroGone && !footerNear;

    return (
        <div
            aria-hidden={!visible}
            inert={!visible}
            className={`fixed inset-x-0 bottom-0 z-10 flex h-[52px] items-center border-t border-lp-rule bg-lp-bg px-4 sm:hidden ${visible ? '' : 'hidden'}`}
        >
            <GetUrlButton className="h-11 w-full" />
        </div>
    );
}
