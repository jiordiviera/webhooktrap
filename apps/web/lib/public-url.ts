/** Public web origin for ingest links and copy-to-clipboard helpers. */
export function getPublicWebOrigin() {
  if (typeof window !== "undefined") {
    return window.location.origin;
  }

  return process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:7777";
}

export function inboxIngestUrl(inboxId: string) {
  return `${getPublicWebOrigin().replace(/\/$/, "")}/hooks/${inboxId}`;
}
