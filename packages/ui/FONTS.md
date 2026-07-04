# Shared Fonts

This UI package exports centralized font configuration for all apps (web, docs, etc.).

## Usage

### In `app/layout.tsx` (Next.js)

```tsx
import { fontClasses } from '@workspace/ui/lib/fonts'

export default function RootLayout({ children }) {
  return (
    <html suppressHydrationWarning className={fontClasses}>
      <body>
        {children}
      </body>
    </html>
  )
}
```

## Available Fonts

### Landing Page (Editorial)
- `--font-cormorant` — Cormorant Garamond 600 (serif, headlines)
- `--font-lora` — Lora 400 (serif, body text)

### App UI (System)
- `--font-bricolage` — Bricolage Grotesque (sans-serif, app headlines)
- `--font-geist-mono` — Geist Mono (monospace, code)
- `--font-jetbrains-mono` — JetBrains Mono 400/500 (monospace, terminal)

## CSS Usage

Use in Tailwind or CSS:

```css
/* Headlines */
h1, h2, h3 {
  font-family: var(--font-bricolage);
}

/* Landing serif */
.landing h1 {
  font-family: var(--font-cormorant);
}

/* Code/terminals */
code {
  font-family: var(--font-jetbrains-mono);
}
```

## Guidelines

✅ **Landing Page** — Use `--font-cormorant` + `--font-lora` (serif, premium feel)  
✅ **Dashboard/App** — Use `--font-bricolage` (sans-serif, clean)  
✅ **Code Blocks** — Use `--font-jetbrains-mono` or `--font-geist-mono`  
❌ **Don't mix** — Keep landing and app typography separate by scope

## Apps Using This

- `apps/web` — Landing + dashboard
- `apps/docs` — Documentation (Fumadocs)
- `apps/videos` — Remotion videos (when added)
