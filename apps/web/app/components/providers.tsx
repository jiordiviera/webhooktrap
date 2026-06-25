"use client";

import type { ReactNode } from "react";
import { ThemeProvider } from "@/app/components/theme-provider";
import { AuthProvider } from "@/contexts/auth-context";
import { TooltipProvider } from "@workspace/ui/components/tooltip";
import { Analytics } from "@vercel/analytics/next";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <Analytics />
      <TooltipProvider>
        <AuthProvider>{children}</AuthProvider>
      </TooltipProvider>
    </ThemeProvider>
  );
}
