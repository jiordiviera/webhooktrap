# Typographie & wordmark

Guide pour reproduire le rendu **DreamBell → Webhook Trap** en design et en code.

## Contexte

Les fichiers PNG/JPG dans `../branding/` (`logo.png`, `icon.jpg`) ont été générés via **Grok Imagine**. Ils **ne contiennent pas de fichier de police** : le texte est dessiné en pixels.

Le style visé (DreamBell → Webhook Trap) correspond à un **serif classique, chaleureux, premium** — pas à une police unique imposée par l’outil. Pour le site, Figma et le code, on utilise les équivalents listés ci-dessous.

## Style typographique (cible marque)

| Élément | Recommandation |
|---------|----------------|
| **Famille** | Serif transitoire / classique (lisible, un peu premium, pas Times cheap) |
| **Graisse** | Regular ou Medium pour tout le mot ; parfois SemiBold sur « Hook » seulement |
| **Casse** | `Webhook Trap` — H majuscule, reste en minuscules (ou title case selon préférence) |
| **Interlettrage** | Légèrement ouvert : `letter-spacing: 0.02em` à `0.06em` |
| **Couleur clair** | Brun terracotta ~ `#6B4A3A` – `#7D5A45` |
| **Couleur dark mode** | Crème ~ `#F5F0E8` – `#E8E0D5` |

## Polices les plus proches

### Gratuites (Google Fonts) — recommandé pour Webhook Trap

| Police | Usage | Note |
|--------|-------|------|
| **[Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond)** | Wordmark, marketing | **La plus proche** d’un logo « DreamBell » élégant |
| [Fraunces](https://fonts.google.com/specimen/Fraunces) | Alternative wordmark | Plus moderne / startup |
| [Lora](https://fonts.google.com/specimen/Lora) | Corps UI + wordmark secondaire | Très équilibrée |
| [Libre Baskerville](https://fonts.google.com/specimen/Libre+Baskerville) | Corps long | Sobre, lisible en petit |

### Recommandation pratique

| Contexte | Police |
|----------|--------|
| **Wordmark / marketing** | Cormorant Garamond **600** (ou 500) |
| **App / docs (corps)** | Lora ou Inter |
| **Logo texte seul** | Cormorant Garamond — pas de sans-serif sur le nom |

### Payantes (optionnel, plus « brand »)

- Playfair Display
- Canela, Noe Display, Reckless

## Tokens couleur wordmark

```css
:root {
  --hookscope-wordmark-light: #6B4A3A;
  --hookscope-wordmark-light-alt: #7D5A45;
  --hookscope-wordmark-dark: #F2EBE3;
  --hookscope-wordmark-dark-alt: #E8E0D5;
  --hookscope-cream-bg: #F5F0E8;
}
```

## CSS prêt à l’emploi

Voir aussi `../branding/brand.css`.

```css
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Lora:wght@400;500&display=swap');

.logo-wordmark {
  font-family: 'Cormorant Garamond', 'Times New Roman', serif;
  font-weight: 600;
  font-size: 1.75rem;
  letter-spacing: 0.04em;
  color: var(--hookscope-wordmark-light, #6B4A3A);
}

.logo-wordmark--dark {
  color: var(--hookscope-wordmark-dark, #F2EBE3);
}

/* Corps app / docs */
.body-text {
  font-family: 'Lora', Georgia, serif;
  font-weight: 400;
}

.ui-text {
  font-family: ui-sans-serif, system-ui, 'Inter', sans-serif;
}
```

## Next.js / Tailwind

```ts
// apps/web — exemple next/font
import { Cormorant_Garamond, Lora } from 'next/font/google'

export const wordmarkFont = Cormorant_Garamond({
  subsets: ['latin'],
  weight: ['500', '600'],
  variable: '--font-wordmark',
})

export const bodyFont = Lora({
  subsets: ['latin'],
  weight: ['400', '500'],
  variable: '--font-body',
})
```

```html
<span class="font-[family-name:var(--font-wordmark)] font-semibold tracking-wide text-[#6B4A3A]">
  Webhook Trap
</span>
```

## Retrouver la police exacte (DreamBell source)

Si tu as le fichier source du logo DreamBell (SVG/Figma) ou une capture nette du seul texte :

1. [WhatTheFont (MyFonts)](https://www.myfonts.com/pages/whatthefont)
2. [Font In Logo](https://www.fontinlogo.com/)

Upload l’image du mot **DreamBell** ou **Webhook Trap** → nom commercial le plus probable.

## Résumé

| Usage | Choix |
|-------|--------|
| Équivalent gratuit du PNG généré | **Cormorant Garamond SemiBold** |
| Clair | `#6B4A3A` |
| Dark | `#F2EBE3` |
| Corps produit | Lora ou Inter |

Ce n’est pas la police « officielle » des PNG Imagine, mais c’est le **meilleur équivalent reproductible** pour site, Figma et README.

## Assets logo

| Fichier | Usage |
|---------|--------|
| `../branding/logo.png` | Wordmark complet (Imagine) — header, README, marketing |
| `../branding/icon.jpg` | Pictogramme / favicon source (Imagine) |

Pour le favicon en prod : exporter `icon.jpg` en `.ico` / `favicon-32.png` ou recréer en SVG à partir du pictogramme.