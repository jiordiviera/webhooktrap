# Hookscope — Branding assets

## Fichiers

| Fichier | Source | Usage |
|---------|--------|--------|
| `logo.png` | Grok Imagine | Wordmark complet (texte + pictogramme). Header, landing, README, OG image |
| `icon.jpg` | Grok Imagine | Pictogramme seul. Favicon source, app icon, avatars sociaux |
| `brand.css` | Maintenu à la main | Tokens couleur + classes `.logo-wordmark` (Cormorant Garamond) |

## Typographie

Les PNG/JPG **n’embarquent pas de police** (texte rasterisé). Pour le code et Figma, utiliser l’équivalent documenté dans [`../docs/08-typographie.md`](../docs/08-typographie.md) :

- **Wordmark** : Cormorant Garamond 600, `#6B4A3A` (clair) / `#F2EBE3` (dark)
- **Corps** : Lora ou Inter

## Usage recommandé

```html
<link rel="stylesheet" href="/branding/brand.css" />

<!-- Image logo (fidèle au rendu Imagine) -->
<img src="/branding/logo.png" alt="Hookscope" width="180" height="48" />

<!-- Ou texte seul (SEO, accessibilité, dark mode) -->
<span class="logo-wordmark">Hookscope</span>
```

Combiner **image** (rendu premium du PNG) + **texte stylé** (accessibilité, `prefers-color-scheme`) est une bonne pratique.

## Historique

- Premiers SVG (cyan / fond sombre) — remplacés par la direction **terracotta / crème** et assets Imagine.
- Direction typographique initialement explorée via le nom de travail **DreamBell**, puis **Hookscope**.