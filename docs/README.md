# Webhook Trap — Documentation projet

Résultat consolidé de nos sessions de brainstorming (juin 2026).

## Index

| Document | Contenu |
|----------|---------|
| [01-vision.md](./01-vision.md) | But du projet, problème, solution, cible |
| [02-parcours-decisions.md](./02-parcours-decisions.md) | PulseSend → migration Adonis → greenfield → Webhook Trap |
| [03-analyse-business.md](./03-analyse-business.md) | Business model, concurrence, viabilité, risques |
| [04-architecture.md](./04-architecture.md) | Stack technique, monorepo, domaine, flux |
| [05-product-spec-mvp.md](./05-product-spec-mvp.md) | User stories, wireframes, API, critères d'acceptation |
| [06-naming-branding.md](./06-naming-branding.md) | Nom, domaines, identité visuelle, handles |
| [07-concurrence.md](./07-concurrence.md) | Cartographie concurrentielle détaillée |
| [08-typographie.md](./08-typographie.md) | Wordmark DreamBell/Webhook Trap, polices, CSS, tokens |
| [09-stack.md](./09-stack.md) | Stack complète, versions, phases |
| [10-hebergement.md](./10-hebergement.md) | **Vercel + Railway + Neon** — setup, DNS, env |

## Statut

- **Phase** : MVP avancé — backend complet, frontend dashboard/settings, auth, two-factor
- **Nom retenu** : Webhook Trap
- **Route `/profile` supprimée** — intégrée dans Settings (`/settings#account`)
- **Prochaine étape** : Share links (Epic D), CLI light (Epic E), tests