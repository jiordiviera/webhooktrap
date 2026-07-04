# Shared Fonts

Centralized font configuration for all Webhook Trap apps.

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

## CSS Variables

- `--font-ui` — Bricolage Grotesque (sans-serif, all UI)
- `--font-mono` — JetBrains Mono 400/500 (monospace, code)

## Usage Examples

```css
/* Default UI font */
body {
  font-family: var(--font-ui);
}

/* Code blocks & terminals */
code, pre {
  font-family: var(--font-mono);
}
```

## Apps Using This

- `apps/web` — Dashboard, landing, UI
- `apps/docs` — Documentation (Fumadocs)
