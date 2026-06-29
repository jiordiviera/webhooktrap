"use client";

export function MethodBadge({ method }: { method: string }) {
  return (
    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold tracking-wide text-primary">
      {method}
    </span>
  );
}
