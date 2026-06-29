"use client";

import { IconPlayerPlay } from "@tabler/icons-react";
import { Button } from "@workspace/ui/components/button";
import { Input } from "@workspace/ui/components/input";
import { Loader } from "@workspace/ui/components/loader";
import { Separator } from "@workspace/ui/components/separator";
import { cn } from "@workspace/ui/lib/utils";
import { ReplayPanelSkeleton } from "@/features/inbox/components/inbox-detail-skeleton";
import { JsonBlock } from "./json-block";
import { ReplayDTO } from "@workspace/types";

function ReplayStatus({ replay }: { replay: ReplayDTO }) {
  if (replay.statusCode) {
    const isSuccess = replay.statusCode >= 200 && replay.statusCode < 300;
    const isError = replay.statusCode >= 400;

    return (
      <span
        className={cn(
          "font-mono text-sm font-semibold tabular-nums",
          isError
            ? "text-destructive"
            : isSuccess
              ? "text-signal"
              : "text-foreground",
        )}
      >
        {replay.statusCode}
      </span>
    );
  }

  return (
    <span className="font-mono text-sm font-semibold text-destructive">
      {replay.errorCode ?? "ERROR"}
    </span>
  );
}

interface ReplayPanelProps {
  selectedEventId: string | null;
  replayUrl: string;
  replaying: boolean;
  replays: ReplayDTO[];
  replaysLoading: boolean;
  onReplayUrlChange: (value: string) => void;
  onSaveReplayUrl: () => void;
  onReplay: () => void;
}

export function ReplayPanel({
  selectedEventId,
  replayUrl,
  replaying,
  replays,
  replaysLoading,
  onReplayUrlChange,
  onSaveReplayUrl,
  onReplay,
}: ReplayPanelProps) {
  return (
    <div className="border-t border-border px-4 py-3">
      <div className="flex flex-col gap-3 sm:flex-row">
        <Input
          value={replayUrl}
          onChange={(event) => onReplayUrlChange(event.target.value)}
          placeholder="http://localhost:7777/webhooks/stripe"
          className="font-mono text-sm"
        />
        <div className="flex shrink-0 gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onSaveReplayUrl}
          >
            Save
          </Button>
          <Button
            type="button"
            size="sm"
            disabled={!selectedEventId || replaying}
            onClick={onReplay}
          >
            {replaying ? (
              <>
                <Loader size="sm" tone="inherit" />
                Replaying
              </>
            ) : (
              <>
                <IconPlayerPlay className="size-4" aria-hidden />
                Replay
              </>
            )}
          </Button>
        </div>
      </div>

      {replaysLoading && replays.length === 0 ? (
        <ReplayPanelSkeleton />
      ) : replays.length > 0 ? (
        <div className="mt-3 space-y-2">
          <Separator />
          <h3 className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
            History
          </h3>
          <ul className="divide-y divide-border rounded-lg border border-border">
            {replays.map((replay) => (
              <li key={replay.id} className="px-3 py-3 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                  <ReplayStatus replay={replay} />
                  {replay.durationMs !== null ? (
                    <span className="text-muted-foreground tabular-nums">
                      {replay.durationMs}ms
                    </span>
                  ) : null}
                  <span className="min-w-0 truncate font-mono text-xs text-muted-foreground">
                    {replay.targetUrl}
                  </span>
                </div>
                {replay.errorMessage ? (
                  <p className="mt-2 text-destructive">{replay.errorMessage}</p>
                ) : null}
                {replay.responseHeaders ? (
                  <div className="mt-3">
                    <p className="mb-1.5 font-medium">
                      Response headers
                    </p>
                    <JsonBlock value={replay.responseHeaders} />
                  </div>
                ) : null}
                {replay.responseBody ? (
                  <pre className="prose mt-2 max-h-40 overflow-auto font-mono text-xs whitespace-pre-wrap text-foreground">
                    {replay.responseBody}
                  </pre>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
