---
name: Webhook Trap
description: Warm terracotta debugger UI with editorial landing and utilitarian product surfaces.
colors:
  background: "oklch(0.96 0.018 78)"
  foreground: "oklch(0.36 0.045 48)"
  wordmark: "oklch(0.38 0.05 46)"
  primary: "oklch(0.42 0.075 46)"
  primary-foreground: "oklch(0.97 0.01 78)"
  secondary: "oklch(0.94 0.025 74)"
  muted: "oklch(0.94 0.02 76)"
  muted-foreground: "oklch(0.52 0.03 52)"
  accent: "oklch(0.92 0.035 72)"
  border: "oklch(0.88 0.02 75)"
  card: "oklch(0.98 0.012 78)"
  destructive: "oklch(0.48 0.12 28)"
  signal: "oklch(0.55 0.12 145)"
  signal-soft: "oklch(0.94 0.04 145)"
  ring: "oklch(0.55 0.06 48)"
typography:
  display:
    fontFamily: "Cormorant Garamond, Times New Roman, serif"
    fontWeight: 600
    lineHeight: 1.1
    letterSpacing: "0.02em"
  headline:
    fontFamily: "Cormorant Garamond, Times New Roman, serif"
    fontWeight: 600
    fontSize: "2rem"
    lineHeight: 1.15
  body:
    fontFamily: "Lora, Georgia, serif"
    fontWeight: 400
    fontSize: "1rem"
    lineHeight: 1.6
  label:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontWeight: 500
    fontSize: "0.875rem"
    lineHeight: 1.4
    letterSpacing: "0.01em"
  code:
    fontFamily: "ui-sans-serif, system-ui, sans-serif"
    fontWeight: 400
    fontSize: "0.8125rem"
    lineHeight: 1.5
rounded:
  sm: "0.375rem"
  md: "0.5rem"
  lg: "0.625rem"
  xl: "0.875rem"
  pill: "9999px"
spacing:
  xs: "0.5rem"
  sm: "0.75rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
  section: "5rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
    height: "2rem"
  button-primary-hover:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
  button-outline:
    backgroundColor: "{colors.background}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  input-default:
    backgroundColor: "transparent"
    textColor: "{colors.foreground}"
    rounded: "{rounded.lg}"
    padding: "0.25rem 0.625rem"
    height: "2rem"
---

# Design System: Webhook Trap

## Overview

**Creative North Star: "The Warm Workbench"**

Webhook Trap looks like a well-lit developer bench: warm cream surfaces, terracotta accents used sparingly, and typography that shifts from editorial serif on the landing to system UI in the debugger. The product register is utilitarian (Stripe/Linear clarity); the brand register is editorial warmth without losing precision.

Density is controlled, not sparse and not bloated. Lists and borders carry structure; cards appear only when grouping related actions (ingest URL reveal, auth panels). Motion is purposeful on marketing surfaces and restrained in-app. Depth comes from tonal layering (background → card → border) more than heavy shadows.

This system explicitly rejects observability dark-blue dashboards, webhook admin bloat, email-marketing chrome, generic purple SaaS templates, crypto neon, glassmorphism decoration, gradient text, and side-stripe accent borders.

**Key Characteristics:**

- OKLCH terracotta/crème palette with tinted neutrals (hue ~48–78), never pure black or white
- Dual typography: Cormorant + Lora for brand/editorial; `font-ui` (system-ui) for product chrome, forms, tables, code
- Restrained accent: terracotta primary on CTAs and selection; `signal` green only for live/success webhook states
- Light theme default: developer at a bright desk; dark mode tokens exist but are not the default posture
- Row-first product layouts over identical card grids
- Landing motion with `prefers-reduced-motion` off switch

## Colors

A warm parchment atmosphere with burnt-clay accents and a secondary live-webhook green.

### Primary

- **Burnt Terracotta** (oklch(0.42 0.075 46) / ~#6F4E3E): Primary buttons, links on hover, focus ring base. The brand anchor. Used on actions that move the webhook flow forward (open inbox, sign in, replay).
- **Cream on Clay** (oklch(0.97 0.01 78)): Text and icons on primary fills.

### Secondary

- **Parchment Wash** (oklch(0.94 0.025 74)): Secondary buttons, nav active pill backgrounds, ticker band (non-white variant).
- **Webhook Signal** (oklch(0.55 0.12 145) / ~#3D8B62): Live indicators, successful capture states, demo pulse accents. Never the primary CTA color.

### Tertiary

- **Soft Signal Mist** (oklch(0.94 0.04 145)): Backgrounds behind live/success badges only.

### Neutral

- **Studio Cream** (oklch(0.96 0.018 78) / ~#F5F0E8): Page background, the default canvas.
- **Ink Brown** (oklch(0.36 0.045 48) / ~#5C4638): Body text, headings on product surfaces.
- **Wordmark Clay** (oklch(0.38 0.05 46)): Logo and brand wordmark tone.
- **Muted Bark** (oklch(0.52 0.03 52)): Secondary labels, table headers, helper copy.
- **Linen Border** (oklch(0.88 0.02 75)): Dividers, input borders, table row separators.
- **Raised Card** (oklch(0.98 0.012 78)): Elevated panels (ingest URL card, auth shell).
- **Error Clay** (oklch(0.48 0.12 28)): Destructive text and invalid field states.

### Named Rules

**The One Accent Rule.** Terracotta primary occupies ≤10% of any product screen. Its scarcity signals importance. Signal green is even rarer: live state only.

**The Tinted Neutral Rule.** Backgrounds, borders, and text neutrals carry a warm hue (chroma 0.005–0.02). Never use untinted #000 or #fff.

## Typography

**Display Font:** Cormorant Garamond (Times New Roman fallback)
**Body Font:** Lora (Georgia fallback)
**UI / Code Font:** ui-sans-serif, system-ui, sans-serif (`font-ui`)

**Character:** Editorial warmth on marketing; crisp system labels in the debugger. The pairing says "premium craft" without sacrificing scan speed in tables and JSON.

### Hierarchy

- **Display** (600, clamp on landing hero, line-height ~1.05): Landing hero headlines only. Cormorant.
- **Headline** (600, 1.75–2rem, line-height 1.15): Section titles on landing (`h2`). Cormorant via base `h1–h4` rule.
- **Title** (600, 1.5rem / text-2xl, line-height 1.2): Product page titles (`Your inboxes`). `font-ui`, not serif.
- **Body** (400, 1rem / 0.9375rem, line-height 1.6): Explanatory copy, max ~65–75ch on marketing paragraphs. Lora default on `body`.
- **Label** (500, 0.875rem / 0.8rem, letter-spacing 0.04em on uppercase labels): Form labels, table headers, uppercase micro-labels (`YOUR INGEST URL`). `font-ui`.
- **Code** (400, 0.8125rem, line-height 1.5): Ingest URLs, JSON paths, HTTP methods. `font-ui`, often `tabular-nums` for counts.

### Named Rules

**The Two Registers Rule.** Landing/marketing uses serif display + Lora body. Dashboard, inbox, auth, and settings use `font-ui` for headings, labels, tables, and code. Do not put Cormorant on data tables or replay panels.

## Elevation

Flat-by-default with soft ambient lift on marketing moments. Product surfaces use border-separated rows on `background`; cards are occasional, not the default container.

Depth hierarchy: `background` < `secondary`/`muted` < `card` < `border` contrast. Shadows are diffuse and low-opacity OKLCH terracotta or neutral, never harsh black drops.

### Shadow Vocabulary

- **Ingest reveal** (`0 12px 40px oklch(0.35 0.04 48 / 0.08)`): Single-use card after creating an inbox on landing.
- **Demo glow** (animated `0 24px 60px` → `0 28px 70px` with signal ring): Live webhook demo panel only.
- **Sticky header** (`backdrop-blur-sm` + `bg-background/95`): Dashboard shell header; blur is functional, not decorative glass.

### Named Rules

**The Flat Product Rule.** Dashboard and inbox UI do not use drop shadows on list rows. Separation is by `border-border` only.

**The Motion-Only Lift Rule.** On landing, elevation may animate (demo glow). In product UI, hover states change tone or border, not shadow stacks.

## Components

### Buttons

- **Shape:** Gently rounded (10px / `rounded-lg`, `--radius: 0.625rem`). Landing CTAs may use `rounded-full` pills (height 44px).
- **Primary:** Burnt terracotta fill, cream text, height 32px default (40px on landing hero). Hover: `bg-primary/80`. Active: 1px translateY (built into shadcn button).
- **Outline:** Background cream, linen border, foreground text. Hover: `bg-muted`.
- **Ghost:** Transparent; hover `bg-muted`. Used for sign out, secondary nav.
- **Focus:** `ring-3 ring-ring/50` with border shift to `ring` color. No glow halos.

### Chips

Not a core primitive yet. When needed: `secondary` background, `foreground` text, `rounded-md`, no side stripes.

### Cards / Containers

- **Corner Style:** `rounded-xl` (14px) for marketing cards; `rounded-lg` for compact product panels.
- **Background:** `card` on `background`.
- **Shadow Strategy:** Marketing only (see Elevation). Product cards: border-first.
- **Border:** `border-border` 1px full border.
- **Internal Padding:** 16px (`p-4`) compact; 24–32px on auth shell.

### Inputs / Fields

- **Style:** 32px height, `rounded-lg`, `border-input`, transparent background, `md:text-sm`.
- **Focus:** `border-ring` + `ring-3 ring-ring/50`.
- **Error:** `border-destructive` + `ring-destructive/20`. Messages via `FieldError`.

### Navigation

- **Marketing header:** Text links, muted default, `hover:text-primary`. Logo image wordmark.
- **Dashboard shell:** Sticky top bar, 56px height, logo + nav pill (`bg-secondary` when active) + user + ghost sign out. `font-ui` throughout.
- **Auth shell:** Split layout; serif title, `font-ui` form.

### Loader

- **Variants:** spinner (default), dots, ring.
- **Tones:** `primary`, `muted`, `signal`, `inherit`.
- **Usage:** Inline actions and centered page loads. Never block entire data tables with a lone spinner; prefer skeleton rows.

### Provider Ticker (signature)

- **Default:** Logos rendered black (`brightness-0`) on white band.
- **Hover:** Color reveal via stacked SVG opacity crossfade; `cursor-pointer`; scroll pauses on track hover.
- **No layout shift** on hover.

### Live Webhook Demo (signature)

- Terminal-like panel with method badge, route string, replay pulse using `signal` and `landing-replay-active` animation.

## Do's and Don'ts

### Do:

- **Do** use OKLCH tokens from `packages/ui/src/styles/globals.css` as the single source of truth.
- **Do** use `font-ui` for dashboard, inbox, auth forms, tables, status codes, and ingest URLs.
- **Do** use Cormorant + Lora on landing/editorial sections only.
- **Do** separate product lists with horizontal borders (`border-b border-border`), not nested cards.
- **Do** use `signal` green exclusively for live/success webhook semantics.
- **Do** respect `prefers-reduced-motion` on landing animations (see `apps/web/app/landing.css`).
- **Do** keep primary terracotta on ≤10% of product screens.
- **Do** provide focus-visible rings on all interactive controls.

### Don't:

- **Don't** default to observability dark-blue dashboards (Datadog / Grafana aesthetic).
- **Don't** build bloated webhook admin UIs (Hookdeck / RequestBin clutter, nested panels, dashboard maze).
- **Don't** use email marketing product chrome (Resend / SendGrid campaign vibes).
- **Don't** use generic SaaS purple Inter templates, hero metrics, or identical icon-card grids.
- **Don't** use crypto neon, glassmorphism-as-decoration, gradient text, or side-stripe accent borders.
- **Don't** put serif display fonts on data tables, replay panels, or dense settings.
- **Don't** use pure #000 or #fff; tint neutrals toward warm hue 48–78.
- **Don't** animate layout properties (width, height, margin, padding) for hover; opacity and color only.
- **Don't** open modals when inline expansion or a dedicated panel suffices (replay, settings, share).