"use client";

import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";

import { cn } from "@workspace/ui/lib/utils";

const TIP_DURATION_MS = 8000;

const DEFAULT_TIPS = [
  {
    key: "anonymous",
    text: "Anonymous inboxes last 48 hours. Sign in when you want to keep them.",
    label: "Hookscope tip",
  },
  {
    key: "replay",
    text: "Replay is not done until you see status, latency, and the response body.",
    label: "Inspect · Replay · Respond",
  },
  {
    key: "providers",
    text: "One ingest URL works for Stripe, GitHub, Shopify, Twilio, or a raw curl.",
    label: "Any provider",
  },
  {
    key: "headers",
    text: "Read headers and raw bytes as received before you trust the parsed JSON.",
    label: "Payload first",
  },
  {
    key: "share",
    text: "Share a read-only inbox link so teammates can inspect without an account.",
    label: "Team debug",
  },
] as const;

type LoaderTip = {
  key: string;
  text: string;
  label: string;
};

const loaderVariants = cva("inline-flex shrink-0 items-center justify-center", {
  variants: {
    variant: {
      spinner: "",
      dots: "gap-1",
      ring: "",
    },
    size: {
      xs: "",
      sm: "",
      md: "",
      lg: "",
      xl: "",
    },
    tone: {
      default: "text-primary",
      muted: "text-muted-foreground",
      signal: "text-signal",
      inherit: "text-current",
    },
  },
  defaultVariants: {
    variant: "spinner",
    size: "md",
    tone: "default",
  },
});

const spinnerSizeClasses = {
  xs: "size-3",
  sm: "size-4",
  md: "size-6",
  lg: "size-8",
  xl: "size-10",
} as const;

const dotSizeClasses = {
  xs: "size-1",
  sm: "size-1.5",
  md: "size-2",
  lg: "size-2.5",
  xl: "size-3",
} as const;

const ringSizeClasses = {
  xs: "size-4",
  sm: "size-5",
  md: "size-7",
  lg: "size-9",
  xl: "size-11",
} as const;

const layoutClasses = {
  inline: "",
  centered: "flex-col gap-3 py-8",
  fullscreen: "min-h-svh w-full flex-col gap-6 px-6",
} as const;

function pickRandomTip(tips: LoaderTip[], exceptKey?: string) {
  const pool =
    exceptKey == null ? tips : tips.filter((tip) => tip.key !== exceptKey);
  return pool[Math.floor(Math.random() * pool.length)] ?? tips[0]!;
}

function QuoteMark({ className }: { className?: string }) {
  return (
    <svg
      className={cn("size-8 text-muted-foreground/50", className)}
      fill="currentColor"
      viewBox="0 0 32 32"
      aria-hidden
    >
      <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z" />
    </svg>
  );
}

function LoadingDots({
  size = "md",
  tone = "default",
  className,
}: {
  size?: keyof typeof dotSizeClasses;
  tone?: VariantProps<typeof loaderVariants>["tone"];
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5",
        loaderVariants({ tone }),
        className,
      )}
      aria-hidden
    >
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          className={cn(
            "rounded-full bg-current motion-reduce:animate-none motion-safe:animate-loader-dot",
            dotSizeClasses[size],
          )}
          style={{ animationDelay: `${i * 150}ms` }}
        />
      ))}
    </span>
  );
}

function LoaderTips({
  tips,
  className,
}: {
  tips: LoaderTip[];
  className?: string;
}) {
  const [tip, setTip] = React.useState<LoaderTip>(() => tips[0]!);
  const [visible, setVisible] = React.useState(true);

  React.useEffect(() => {
    setTip(pickRandomTip(tips));
    const interval = window.setInterval(() => {
      setVisible(false);
      window.setTimeout(() => {
        setTip((current) => pickRandomTip(tips, current.key));
        setVisible(true);
      }, 280);
    }, TIP_DURATION_MS);

    return () => window.clearInterval(interval);
  }, [tips]);

  return (
    <div className={cn("flex w-full max-w-xl flex-col items-center gap-6", className)}>
      <QuoteMark />

      <div className="relative min-h-28 w-full">
        <figure
          className={cn(
            "flex flex-col items-center gap-3 text-center transition-all duration-500 ease-out motion-reduce:transition-none",
            visible ? "translate-y-0 opacity-100" : "-translate-y-2 opacity-0",
          )}
        >
          <blockquote className="font-ui text-lg leading-relaxed text-foreground/90 italic sm:text-xl">
            &ldquo;{tip.text}&rdquo;
          </blockquote>
          <figcaption className="font-ui text-sm text-muted-foreground">
            &mdash; {tip.label}
          </figcaption>
        </figure>
      </div>

      <div className="flex flex-col items-center gap-4">
        <div className="h-0.5 w-40 overflow-hidden rounded-full bg-border">
          <div
            key={tip.key}
            className="h-full bg-primary motion-safe:animate-loader-progress motion-reduce:w-full"
            style={{ animationDuration: `${TIP_DURATION_MS}ms` }}
          />
        </div>
        <LoadingDots size="sm" tone="muted" />
      </div>
    </div>
  );
}

type LoaderProps = React.ComponentProps<"div"> &
  VariantProps<typeof loaderVariants> & {
    /** Accessible label. Also shown when `showLabel` is true. */
    label?: string;
    showLabel?: boolean;
    layout?: keyof typeof layoutClasses;
    indicatorClassName?: string;
    /** Rotating tips for fullscreen layout. Defaults to true when layout is fullscreen. */
    showTips?: boolean;
    tips?: LoaderTip[];
    /** Optional slot below tips (logo, illustration, etc.). */
    media?: React.ReactNode;
  };

function LoaderIndicator({
  variant = "spinner",
  size = "md",
  tone = "default",
  indicatorClassName,
}: {
  variant?: NonNullable<VariantProps<typeof loaderVariants>["variant"]>;
  size?: keyof typeof spinnerSizeClasses;
  tone?: VariantProps<typeof loaderVariants>["tone"];
  indicatorClassName?: string;
}) {
  const resolvedSize = size ?? "md";

  if (variant === "dots") {
    return (
      <LoadingDots
        size={resolvedSize}
        tone={tone}
        className={cn(loaderVariants({ variant, size, tone }), indicatorClassName)}
      />
    );
  }

  if (variant === "ring") {
    return (
      <span
        className={cn(
          "relative inline-flex items-center justify-center motion-reduce:animate-none",
          ringSizeClasses[resolvedSize],
          loaderVariants({ tone }),
          indicatorClassName,
        )}
        aria-hidden
      >
        <span
          className={cn(
            "absolute inset-0 motion-safe:animate-spin rounded-full border-2 border-current/25 motion-reduce:animate-none",
            ringSizeClasses[resolvedSize],
          )}
        />
        <span
          className={cn(
            "absolute inset-[18%] motion-safe:animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none",
            "[animation-direction:reverse] [animation-duration:1.1s]",
          )}
        />
      </span>
    );
  }

  return (
    <svg
      aria-hidden
      className={cn(
        "motion-safe:animate-spin text-current motion-reduce:animate-none",
        spinnerSizeClasses[resolvedSize],
        loaderVariants({ tone }),
        indicatorClassName,
      )}
      viewBox="0 0 100 101"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
        fill="currentColor"
        className="opacity-20"
      />
      <path
        d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
        fill="currentColor"
      />
    </svg>
  );
}

function Loader({
  className,
  indicatorClassName,
  variant = "spinner",
  size = "md",
  tone = "default",
  label = "Loading",
  showLabel = false,
  layout = "inline",
  showTips,
  tips = [...DEFAULT_TIPS],
  media,
  ...props
}: LoaderProps) {
  const isBlockLayout = layout !== "inline";
  const shouldShowTips = showTips ?? layout === "fullscreen";
  const isImmersive = isBlockLayout && shouldShowTips;

  if (isImmersive) {
    return (
      <div
        role="status"
        aria-live="polite"
        aria-busy="true"
        className={cn(
          "relative flex items-center justify-center overflow-hidden bg-background text-center",
          layoutClasses[layout],
          className,
        )}
        {...props}
      >
        <div
          className="pointer-events-none absolute -top-16 -left-24 size-72 rounded-full bg-primary/10 blur-3xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -right-24 -bottom-16 size-72 rounded-full bg-accent blur-3xl"
          aria-hidden
        />

        <div className="relative z-10 flex w-full max-w-xl flex-col items-center gap-8">
          {showLabel && label ? (
            <p className="font-ui text-sm text-muted-foreground">{label}</p>
          ) : null}

          <LoaderTips tips={tips} />

          {media ? <div className="opacity-80 grayscale">{media}</div> : null}

          <span className="sr-only">{label}</span>
        </div>
      </div>
    );
  }

  return (
    <div
      role="status"
      aria-live="polite"
      aria-busy="true"
      className={cn(
        isBlockLayout && "flex items-center justify-center text-center",
        layoutClasses[layout],
        className,
      )}
      {...props}
    >
      <LoaderIndicator
        variant={variant ?? "spinner"}
        size={size ?? "md"}
        tone={tone}
        indicatorClassName={indicatorClassName}
      />
      <span
        className={
          showLabel ? "font-ui text-sm text-muted-foreground" : "sr-only"
        }
      >
        {label}
      </span>
    </div>
  );
}

export { Loader, loaderVariants, DEFAULT_TIPS, TIP_DURATION_MS };
export type { LoaderProps, LoaderTip };