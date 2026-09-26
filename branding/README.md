# Webhook Trap — Branding assets

## Fichiers

| Fichier | Source | Usage |
|---------|--------|--------|
| `logo.png` | Identité géométrique | Wordmark complet (texte + pictogramme). Header, landing, README, OG image |
| `icon.png` | Identité géométrique | Pictogramme transparent, favicon source et app icon. `icon.jpg` est conservé pour compatibilité |
| `brand.css` | Maintenu à la main | Tokens couleur + classes `.logo-wordmark` (Cormorant Garamond) |

## Typographie

Les PNG/JPG **n’embarquent pas de police** (texte rasterisé). Pour le code et Figma, utiliser l’équivalent documenté dans [`../docs/08-typographie.md`](../docs/08-typographie.md) :

- **Wordmark** : Cormorant Garamond 600, `#6B4A3A` (clair) / `#F2EBE3` (dark)
- **Corps** : Lora ou Inter

## Usage recommandé

```html
<link rel="stylesheet" href="/branding/brand.css" />

<!-- Wordmark rasterisé -->
<img src="/branding/logo.png" alt="Webhook Trap" width="180" height="48" />

<!-- Ou texte seul (SEO, accessibilité, dark mode) -->
<span class="logo-wordmark">Webhook Trap</span>
```

Combiner **image** (rendu premium du PNG) + **texte stylé** (accessibilité, `prefers-color-scheme`) est une bonne pratique.

## Historique

- Symbole initial en cloche remplacé par le symbole cuivré fourni par Jiordi. Les formats favicon et app icon dérivent du même symbole.
- Direction typographique initialement explorée via le nom de travail **DreamBell**, puis **Webhook Trap**.