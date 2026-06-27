"use client";

import { SidebarProvider } from "@workspace/ui/components/sidebar";
import { DashboardNavProvider } from "@/features/dashboard/context/dashboard-nav-context";
import { InboxPageProvider } from "@/features/inbox/context/inbox-page-context";
import { AppSidebar } from "./app-sidebar";
import { DashboardHeader } from "./dashboard-header";
import { MobileSearchFab } from "./mobile-search-fab";
import { PropsWithChildren } from "react";

export function DashboardShell({ children }: PropsWithChildren) {
  return (
    <DashboardNavProvider>
      <InboxPageProvider>
        <SidebarProvider>
          <AppSidebar />
          <div className="h-svh w-full overflow-hidden">
            <div className="bg-background flex h-full w-full flex-col">
              <DashboardHeader />
              <div className="custom-scrollbar flex w-full flex-1 flex-col overflow-auto">
                <main className="flex-1 p-4 sm:p-6">{children}</main>
              </div>
            </div>
          </div>
          <MobileSearchFab />
        </SidebarProvider>
      </InboxPageProvider>
    </DashboardNavProvider>
  );
}
