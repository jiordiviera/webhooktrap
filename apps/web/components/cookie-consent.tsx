"use client";

import { useEffect, useState } from "react";
import Link from "next/link";

const CONSENT_KEY = "hs:consent";

export function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const acknowledged = localStorage.getItem(CONSENT_KEY) === "true";
    if (!acknowledged) {
      // Small delay so it doesn't flash on fast navigations
      const timer = setTimeout(() => setVisible(true), 600);
      return () => clearTimeout(timer);
    }
  }, []);

  function accept() {
    localStorage.setItem(CONSENT_KEY, "true");
    setVisible(false);
  }

  if (!visible) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 backdrop-blur-sm">
      <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
          This site uses cookies for analytics and essential functionality.{" "}
          <Link
            href="/privacy"
            className="underline underline-offset-2 transition-colors hover:text-foreground"
          >
            Learn more
          </Link>
        </p>
        <button
          type="button"
          onClick={accept}
          className="shrink-0 rounded-md border border-border bg-foreground px-4 py-1.5 text-xs font-medium text-background transition-colors hover:bg-foreground/90"
        >
          Accept
        </button>
      </div>
    </div>
  );
}
