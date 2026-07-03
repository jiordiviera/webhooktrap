import { AbsoluteFill } from "remotion";
import { TerminalSimulator, type TerminalLine } from "../remocn/terminal-simulator";

// Mirrors the real snippets in apps/web/components/landing/how-it-works-section.tsx
// (Receive / Inspect / Replay) so the video and the landing copy never drift apart.
const LINES: TerminalLine[] = [
  { text: "curl -X POST hookscope.dev/i/xK9m2pQ7nR4a \\", type: "command", delay: 6 },
  {
    text: "-d '{\"type\":\"checkout.session.completed\"}'",
    type: "command",
    delay: 4,
    continuation: true,
  },
  { text: "{ \"received\": true }", type: "success", delay: 10 },
  { text: "GET /i/xK9m2pQ7nR4a/events/evt_8f2a", type: "command", delay: 12 },
  { text: "{ \"method\": \"POST\", \"stripe-signature\": \"...\" }", type: "log", delay: 8 },
  { text: "POST /replay --to localhost:7777/webhooks", type: "command", delay: 12 },
  { text: "→ 200 OK · 142ms", type: "success", delay: 10 },
  { text: "← { \"received\": true }", type: "log", delay: 6 },
];

export function TerminalBeat() {
  return (
    <AbsoluteFill style={{ backgroundColor: "oklch(0.21 0.04 42)" }}>
      <TerminalSimulator lines={LINES} title="~/hookscope" prompt="$" fontSize={18} />
    </AbsoluteFill>
  );
}
