"use client";

import type { ReactNode } from "react";
import { AuthProvider } from "@/contexts/auth-context";
import { TooltipProvider } from "@workspace/ui/components/tooltip";

export function Providers({ children }: { children: ReactNode }) {
  return (
    <TooltipProvider>
      <AuthProvider>{children}</AuthProvider>
    </TooltipProvider>
  );
}
