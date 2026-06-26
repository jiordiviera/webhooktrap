"use client";

import type { ReactNode } from "react";
import { ThemeProvider } from "@/app/components/theme-provider";
import { AuthProvider } from "@/contexts/auth-context";
import { ConfirmProvider } from "@/contexts/confirm-context";
import { QueryProvider } from "@/contexts/query-provider";
import { TooltipProvider } from "@workspace/ui/components/tooltip";
import { Analytics } from "@vercel/analytics/next";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <Analytics />
      <TooltipProvider>
        <QueryProvider>
          <AuthProvider>
            <ConfirmProvider>{children}</ConfirmProvider>
          </AuthProvider>
        </QueryProvider>
      </TooltipProvider>
    </ThemeProvider>
  );
}
