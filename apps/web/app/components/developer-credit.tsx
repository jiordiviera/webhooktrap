import { cn } from "@workspace/ui/lib/utils";

const SITE_URL = "https://jiordiviera.me";

type DeveloperCreditProps = {
  variant?: "default" | "sidebar";
  className?: string;
};

export function DeveloperCredit({
  variant = "default",
  className,
}: DeveloperCreditProps) {
  const isSidebar = variant === "sidebar";

  const linkClass = cn(
    "underline-offset-4 transition-colors hover:underline ",
    isSidebar
      ? "text-sidebar-foreground/80 hover:text-sidebar-foreground"
      : "text-foreground/80 hover:text-primary",
  );


  return (
    <p
      className={cn(
        "font-ui inline-flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[0.6875rem] leading-relaxed",
        isSidebar ? "text-sidebar-foreground/50" : "text-muted-foreground",
        className,
      )}
    >
      <span>Built by</span>
      <a
        href={SITE_URL}
        target="_blank"
        rel="noopener noreferrer"
        className={linkClass}
      >
        Jiordi Kengne
      </a>
    </p>
  );
}
