"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { IconCheck, IconCopy, IconShare3, IconX } from "@tabler/icons-react";
import { Button } from "@workspace/ui/components/button";
import { Loader } from "@workspace/ui/components/loader";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@workspace/ui/components/sheet";
import { EventInspectorSkeleton } from "@/features/inbox/components/inbox-detail-skeleton";
import {
  useEventDetailQuery,
  useEventReplaysQuery,
  eventReplaysQueryKey,
} from "@/features/inbox/hooks/use-event-queries";
import { inboxQueryKey } from "@/features/inbox/hooks/use-inbox-query";
import { ReplayPanel } from "@/features/inbox/components/replay-panel";
import { ApiError } from "@/lib/api";
import { buildEventCurl, buildEventJson } from "@/lib/event-copy";
import { generateShareToken, replayEvent } from "@/lib/events";
import { formatRelativeTime, updateInbox } from "@/lib/inboxes";
import type { ReplayRecord } from "@/lib/events";
import JsonView from "@uiw/react-json-view";
import { JsonBlock } from "./json-block";

function MethodBadge({ method }: { method: string }) {
  return (
    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold tracking-wide text-primary">
      {method}
    </span>
  );
}

interface EventDetailSheetProps {
  inboxId: string;
  selectedEventId: string | null;
  onClose: () => void;
}

export function EventDetailSheet({
  inboxId,
  selectedEventId,
  onClose,
}: EventDetailSheetProps) {
  const queryClient = useQueryClient();

  const eventQuery = useEventDetailQuery(selectedEventId);
  const replaysQuery = useEventReplaysQuery(selectedEventId);

  const eventDetail = eventQuery.data ?? null;
  const replays = replaysQuery.data ?? [];
  const detailLoading = eventQuery.isLoading && !eventDetail;
  const replaysLoading =
    replaysQuery.isLoading && replays.length === 0 && Boolean(selectedEventId);

  const [replayUrl, setReplayUrl] = useState("");
  const [replaying, setReplaying] = useState(false);
  const [copiedAction, setCopiedAction] = useState<"json" | "curl" | null>(
    null,
  );
  const [sharing, setSharing] = useState(false);
  const [shareUrl, setShareUrl] = useState<string | null>(null);
  const [shareJustCopied, setShareJustCopied] = useState(false);
  const [sheetError, setSheetError] = useState<string | null>(null);

  useEffect(() => {
    setReplayUrl("");
    setShareUrl(null);
    setShareJustCopied(false);
    setSheetError(null);
  }, [selectedEventId]);

  async function handleSaveReplayUrl() {
    try {
      const updated = await updateInbox(inboxId, {
        defaultReplayUrl: replayUrl.trim() || null,
      });
      queryClient.setQueryData(inboxQueryKey(inboxId), updated);
      setReplayUrl(updated.defaultReplayUrl ?? "");
      setSheetError(null);
    } catch (error) {
      setSheetError(
        error instanceof ApiError
          ? error.message
          : "Could not save replay URL.",
      );
    }
  }

  async function handleReplay() {
    if (!selectedEventId) return;

    setReplaying(true);
    setSheetError(null);

    try {
      const replay = await replayEvent(selectedEventId, {
        targetUrl: replayUrl.trim() || undefined,
      });
      queryClient.setQueryData(
        eventReplaysQueryKey(selectedEventId),
        (current: ReplayRecord[] | undefined) => [replay, ...(current ?? [])],
      );
    } catch (error) {
      setSheetError(
        error instanceof ApiError ? error.message : "Replay failed.",
      );
    } finally {
      setReplaying(false);
    }
  }

  async function handleCopyEvent(action: "json" | "curl") {
    if (!eventDetail) return;

    const text =
      action === "json"
        ? buildEventJson(eventDetail)
        : buildEventCurl(eventDetail);
    if (!text) return;

    await navigator.clipboard.writeText(text);
    setCopiedAction(action);
    window.setTimeout(() => setCopiedAction(null), 2000);
  }

  async function handleShare() {
    if (!selectedEventId) return;

    setSharing(true);
    setSheetError(null);

    try {
      const shareToken = await generateShareToken(selectedEventId);
      const origin = window.location.origin;
      setShareUrl(`${origin}/s/${shareToken}`);
    } catch (error) {
      setSheetError(
        error instanceof ApiError
          ? error.message
          : "Could not generate share link.",
      );
    } finally {
      setSharing(false);
    }
  }

  async function handleCopyShareUrl() {
    if (!shareUrl) return;

    await navigator.clipboard.writeText(shareUrl);
    setShareJustCopied(true);
    window.setTimeout(() => setShareJustCopied(false), 2000);
  }

  function dismissShareUrl() {
    setShareUrl(null);
    setShareJustCopied(false);
  }

  const handleClose = useCallback(() => {
    setShareUrl(null);
    setShareJustCopied(false);
    setSheetError(null);
    onClose();
  }, [onClose]);

  return (
    <Sheet
      open={!!selectedEventId}
      onOpenChange={(open) => {
        if (!open) handleClose();
      }}
    >
      <SheetContent
        side="right"
        className="flex w-full flex-col p-0 sm:max-w-lg"
      >
        <SheetHeader className="border-b border-border px-4 py-3">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0 flex-1">
              {eventDetail ? (
                <div className="flex flex-wrap items-center gap-2">
                  <MethodBadge method={eventDetail.method} />
                  <code className="truncate font-mono text-sm text-foreground">
                    {eventDetail.path}
                  </code>
                </div>
              ) : (
                <SheetTitle className="font-ui text-sm font-semibold text-foreground">
                  Event detail
                </SheetTitle>
              )}
              <SheetDescription className="mt-0.5 text-xs">
                {eventDetail
                  ? formatRelativeTime(eventDetail.receivedAt)
                  : detailLoading
                    ? "Loading event..."
                    : ""}
              </SheetDescription>
            </div>
          </div>
          {eventDetail ? (
            <div className="mt-2 flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={sharing}
                onClick={() => void handleShare()}
              >
                {sharing ? (
                  <Loader size="sm" tone="inherit" />
                ) : (
                  <IconShare3 className="size-3.5" aria-hidden />
                )}
                {sharing ? "Creating\u2026" : "Share"}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void handleCopyEvent("json")}
              >
                <IconCopy className="size-3.5" aria-hidden />
                {copiedAction === "json" ? "Copied" : "JSON"}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void handleCopyEvent("curl")}
              >
                <IconCopy className="size-3.5" aria-hidden />
                {copiedAction === "curl" ? "Copied" : "cURL"}
              </Button>
            </div>
          ) : null}
        </SheetHeader>

        <div className="flex-1 overflow-y-auto">
          {sheetError ? (
            <p className="px-4 py-2.5 text-sm text-destructive" role="alert">
              {sheetError}
            </p>
          ) : null}

          {shareUrl ? (
            <div className="flex items-center gap-2 border-b border-border px-4 py-2.5">
              <code className="min-w-0 flex-1 truncate font-mono text-xs text-foreground">
                {shareUrl}
              </code>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void handleCopyShareUrl()}
              >
                {shareJustCopied ? (
                  <IconCheck className="size-3.5 text-signal" aria-hidden />
                ) : (
                  <IconCopy className="size-3.5" aria-hidden />
                )}
                {shareJustCopied ? "Copied" : "Copy"}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={dismissShareUrl}
                aria-label="Dismiss share link"
              >
                <IconX className="size-3.5" aria-hidden />
              </Button>
            </div>
          ) : null}

          {detailLoading ? (
            <EventInspectorSkeleton />
          ) : !eventDetail ? (
            <p className="px-4 py-10 text-sm text-muted-foreground">
              {selectedEventId
                ? "Could not load this event. Select another or refresh."
                : "Select an event to inspect headers and body."}
            </p>
          ) : (
            <div className="space-y-6 p-4">
              <div>
                <h3 className="mb-2 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                  Headers
                </h3>
                <JsonBlock value={eventDetail.headers} />
              </div>

              {eventDetail.query &&
                Object.keys(eventDetail.query).length > 0 && (
                  <div>
                    <h3 className="mb-2 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      Query
                    </h3>
                    <JsonBlock value={eventDetail.query} />
                  </div>
                )}

              <div>
                <h3 className="mb-2 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                  Body
                </h3>
                {eventDetail.bodyJson ? (
                  <JsonBlock value={eventDetail.bodyJson} />
                ) : (
                  <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-muted/30 p-3 font-mono text-xs whitespace-pre-wrap text-foreground">
                    {"(empty)"}
                  </pre>
                )}
              </div>
            </div>
          )}
        </div>

        <ReplayPanel
          selectedEventId={selectedEventId}
          replayUrl={replayUrl}
          replaying={replaying}
          replays={replays}
          replaysLoading={replaysLoading}
          onReplayUrlChange={setReplayUrl}
          onSaveReplayUrl={() => void handleSaveReplayUrl()}
          onReplay={() => void handleReplay()}
        />
      </SheetContent>
    </Sheet>
  );
}
