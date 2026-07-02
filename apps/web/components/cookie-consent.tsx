"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useConsent } from "@/contexts/consent-context";

export function CookieConsent() {
  const { status, accept, decline } = useConsent();
  const [delayElapsed, setDelayElapsed] = useState(false);

  useEffect(() => {
    if (status !== null) return;
    // Small delay so it doesn't flash on fast navigations
    const timer = setTimeout(() => setDelayElapsed(true), 600);
    return () => clearTimeout(timer);
  }, [status]);

  if (status !== null || !delayElapsed) return null;

  return (
    <div
      role="dialog"
      aria-label="Cookie consent"
      aria-live="polite"
      className="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 backdrop-blur-sm"
    >
      <div className="mx-auto flex w-full max-w-6xl flex-col items-start gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6">
        <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
          We use privacy-friendly analytics to see what&apos;s working. No
          tracking cookies, only with your consent.{" "}
          <Link
            href="/privacy"
            className="underline underline-offset-2 transition-colors hover:text-foreground"
          >
            Learn more
          </Link>
        </p>
        <div className="flex shrink-0 gap-2">
          <button
            type="button"
            onClick={decline}
            className="rounded-md border border-border px-4 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
          >
            Decline
          </button>
          <button
            type="button"
            onClick={accept}
            className="rounded-md border border-border bg-foreground px-4 py-1.5 text-xs font-medium text-background transition-colors hover:bg-foreground/90"
          >
            Accept
          </button>
        </div>
      </div>
    </div>
  );
}
