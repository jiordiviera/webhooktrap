"use client";

import Link from "next/link";
import { Button } from "@workspace/ui/components/button";
import { Loader } from "@workspace/ui/components/loader";
import { useAuth } from "@/contexts/auth-context";

export function AuthNav() {
  const { user, status, isAuthenticated } = useAuth();

  if (status === "loading") {
    return <Loader size="sm" tone="muted" label="Checking session" />;
  }

  if (isAuthenticated && user) {
    return (
      <Button asChild>
        <Link href="/dashboard">Dashboard</Link>
      </Button>
    );
  }

  return (
    <Button asChild>
      <Link href="/login">Sign in</Link>
    </Button>
  );
}
