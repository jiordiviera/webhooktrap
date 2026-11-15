# A04 brief: build a direction in Claude Design (Phase 1b)

Author: A00 · 2026-11-11 · Shared by the three A04 runs (one per territory).

## Goal

Build the direction in **Claude Design**, i.e. two Claude artifacts made with
the Artifact tool, not as Markdown or loose HTML. The product owner (PO) will
review them as finished product proposals:

1. A **Design System** artifact (type
   `https://claude.ai/artifact/5M7UeXXcx16TP3vzVFNDzd`) named
   `Webhook Trap · <Territory>`, containing:
   - a README brand book (idea, voice and copy examples, logo usage,
     iconography rules, layout principles, motion);
   - `tokens.json` with **light and dark** themes for colour, type, spacing
     and radius;
   - the logo and favicon as SVG assets;
   - the icon set (8–10 key icons) as SVG assets;
   - component previews: severity set, signature chip set, agent pill set,
     run status set, live indicator, buttons, the territory's signature
     timeline component;
   - the cover.
2. A **Design** canvas (type
   `https://claude.ai/artifact/QKN21svewxgyPb6SYRqWnd`) named
   `Webhook Trap · <Territory> · Screens`, with the Design System installed
   as its design system. It holds the artboards listed below, grouped with
   title notes per screen.

Follow each type's own instructions (the create result carries its
SKILL.md). Read the references they name before writing: the canvas's
`artifact-type/reference/format.md` and `craft.md`, and the system's
`format.md` and `cover.md`. Files go one per call, as the canvas
instructions require.

## Inputs (read in this order)

All paths are relative to
`/home/jiordiviera/workspace/personal/webhooktrap/.worktrees/p1b-design/project/design/`.

1. `brand-territories.md`: your territory's section and the comparison table.
2. `ux-foundations.md`: all of it, especially §0, §4 (screens), §5 (states),
   §7 (accessibility), §8 (responsive) and §9 (frame list).
3. `ux-<territory>.md`: your territory's wireframes, components and risks.
4. `realistic-data.md` and `realistic-data.json`: the **only** content source.
   Use its IDs, numbers, timestamps, payloads and copy exactly.
5. For context:
   `/home/jiordiviera/workspace/personal/webhooktrap/.worktrees/p1b-product/project/product/core-experience.md`
   Part 2.

**Forbidden:** anything from 1.x (screens, `DESIGN.md`, `PRODUCT.md`,
`branding/`), the superseded directions (Field Recorder, Printout, Postmark,
`.worktrees/a02-brand/`), the other two territories' files except the
comparison table, and the existing "PulseSend" design system. **Do not use
any design system other than the one you create.**

## Artboards (minimum, per `ux-foundations.md` §9)

| Screen | Desktop 1440 | Mobile 390 |
| ------ | ------------ | ---------- |
| Landing | full page, light **and** dark | full page, light **and** dark |
| Capture stream (main app) | receiving with #01 selected (light + dark) · waiting for first event (guest) · guest quota reached | receiving list (light + dark) · waiting |
| Delivery detail | #01 Verdict (light + dark) · #01 Raw · #09 invalid signature · #08 no secret | #09 invalid · #01 Raw |
| Run (replay and responses) | **findings with F2 open, D3 inspector, compared with D2** (light + dark) · running (D9 of 14) · draft with agent not installed · passed (#4) · aborted (#2) · inconclusive (#1) | findings report (light + dark) · D3 inspector sheet |
| States sheet | loading skeletons for capture, detail and run · errors (target unreachable, agent disconnected mid-run, guest expired) · empty states | — |

Pages are fluid (`expand: "fill"`) at 1440. Mobile artboards are fixed at
390 wide; make them taller if the content needs it. Group artboards on canvas
pages or rows by screen, with a `title1` note per group. Name every frame.

## Quality bar

- This is a product proposal, not a moodboard. Every screen must be usable
  as drawn: real hierarchy, real density, real data.
- The territory's idea has to shape the layout and the key components, not
  just the colours and fonts.
- Contrast is ≥ 4.5:1 for all text in both themes, and 3:1 for large text,
  icons and focus rings. Severity is never shown by colour alone. Compute the
  ratios (a small script is fine) and record them in the README.
- Accessible markup: real buttons, links and labels; `aria-label` on icon
  buttons. Fonts: Google Fonts with OSS licences only. No emoji, no gradient
  washes, no glassmorphism, no left-border cards.
- Keep A03's honest severity encoding: the duplicate order (F2) is **High**,
  and Critical is reserved for the tampered delivery.

## Rules

- Write local source files only under
  `.worktrees/p1b-design/project/design/claude-design/<territory>/` (use it
  as each artifact's `root`). Do not commit, push or install anything.
- Do not touch other worktrees, the main checkout or `docs/`.
- Create exactly one Design System and one Design canvas. Never create
  duplicates; reuse their `url`.
- **If the Artifact tool, a type, an upload or a publish is unavailable or
  refused, stop and report the exact error.** Never claim an artboard exists
  unless its publish succeeded.

## Report back (≤ 350 words)

- Both artifact URLs.
- Artboards actually published (count and list), and any that failed, with
  the error.
- How the territory's idea shows in the screens.
- Contrast results.
- Deviations from A03's UX and why.
- What you could not do.
