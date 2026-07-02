"use client";

import type { ReactNode } from "react";
import { ThemeProvider } from "@/app/components/theme-provider";
import { AuthProvider } from "@/contexts/auth-context";
import { ConfirmProvider } from "@/contexts/confirm-context";
import { ConsentProvider, useConsent } from "@/contexts/consent-context";
import { QueryProvider } from "@/contexts/query-provider";
import { TooltipProvider } from "@workspace/ui/components/tooltip";
import { Analytics } from "@vercel/analytics/next";
import { CookieConsent } from "@/components/cookie-consent";

function ConsentedAnalytics() {
  const { status } = useConsent();
  if (status !== "accepted") return null;
  return <Analytics />;
}

export function Providers({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <ConsentProvider>
        <ConsentedAnalytics />
        <TooltipProvider>
          <QueryProvider>
            <AuthProvider>
              <ConfirmProvider>
                {children}
                <CookieConsent />
              </ConfirmProvider>
            </AuthProvider>
          </QueryProvider>
        </TooltipProvider>
      </ConsentProvider>
    </ThemeProvider>
  );
}
