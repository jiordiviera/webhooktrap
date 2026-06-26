"use client";

import type { ReactNode } from "react";
import { ThemeProvider } from "@/app/components/theme-provider";
import { AuthProvider } from "@/contexts/auth-context";
import { ConfirmProvider } from "@/contexts/confirm-context";
import { TooltipProvider } from "@workspace/ui/components/tooltip";
import { Analytics } from "@vercel/analytics/next";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <Analytics />
      <TooltipProvider>
        <AuthProvider>
          <ConfirmProvider>{children}</ConfirmProvider>
        </AuthProvider>
      </TooltipProvider>
    </ThemeProvider>
  );
}
