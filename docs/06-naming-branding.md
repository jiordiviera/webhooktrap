# Naming & branding

Recherche effectuée le **22 juin 2026** (WHOIS via who.is).

## Nom retenu : Hookvane

> *Microscope for your webhooks.*

| Élément | Valeur |
|---------|--------|
| **Tagline** | Inspect. Replay. See the response. |
| **Hero** | Debug webhooks in seconds. |
| **CLI** | `npx @hookscope/cli` → commande `hs` |
| **npm scope** | `@hookscope/cli`, `@hookscope/sdk` |
| **Inbox URL** | `https://hookscope.dev/i/:id` |

## Domaines

### Pris / à éviter

| Domaine | Statut |
|---------|--------|
| `replayhook.com` | Pris (03/02/2026) |
| `hookreplay.com` | Pris — concurrent |
| `hookreplay.dev` | Existe |
| `hookdebug.com` | Pris |
| `catchhook.app` | Produit live |

### Recommandés (registrar maintenant)

| Domaine | Prix estimé/an |
|---------|----------------|
| **`hookscope.dev`** | ~$12 |
| `hookscope.io` | ~$35 (backup) |
| `webhookdock.com` | ~$12 (alternative) |

Registrar recommandé : **Cloudflare Registrar**.

## Alternatives évaluées

| Nom | Score | Note |
|-----|-------|------|
| **Hookvane** | 34/40 | Recommandé |
| Hooktrace | 33/40 | Bon backup |
| Deadletter | 33/40 | Mémorable, niche dev |
| Replayhook | 30/40 | Conflit HookReplay |
| Webhookdock | 31/40 | Explicite, long |

## Identité visuelle

Direction actuelle : **serif classique, chaleureux, premium** (rendu DreamBell → Hookvane via Grok Imagine).

| | Valeur |
|--|--------|
| **Wordmark clair** | `#6B4A3A` – `#7D5A45` (terracotta) |
| **Wordmark dark** | `#F2EBE3` – `#E8E0D5` (crème) |
| **Fond clair** | `#F5F0E8` (crème) |
| **Typo wordmark** | [Cormorant Garamond](https://fonts.google.com/specimen/Cormorant+Garamond) 600 |
| **Typo corps** | Lora ou Inter |
| **UI technique** | Inter / Geist (dashboard, code) |

Détail complet : [08-typographie.md](./08-typographie.md)

### Assets logo (`../branding/`)

| Fichier | Source | Usage |
|---------|--------|--------|
| `logo.png` | Grok Imagine | Wordmark complet |
| `icon.jpg` | Grok Imagine | Pictogramme / favicon source |
| `brand.css` | Code | Tokens + classes wordmark |

Les PNG ne contiennent pas de police embarquée — en code, utiliser `brand.css` + Cormorant Garamond pour coller au rendu.

## Handles à réserver

- [ ] `github.com/hookscope`
- [ ] `@hookscope` sur X
- [ ] npm org `@hookscope`
- [ ] `hookscope.dev` + `hookscope.io`

## Copy

| Contexte | Texte |
|----------|-------|
| CTA landing | Open inbox / Try without signup |
| CLI | `$ npx @hookscope/cli inbox` |
| Empty state | Waiting for webhooks… |
| Erreur replay | Is your server running? |