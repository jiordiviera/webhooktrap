# Concurrence

## Carte du marché

| Produit | Capture | Replay | Tunnel localhost | Équipe | Prix entrée |
|---------|---------|--------|------------------|--------|-------------|
| [webhook.site](https://webhook.site) | ✓✓ | ✓ payant | ✓ CLI `whcli` | ✓ | ~$7.5/mois |
| [Hookdeck](https://hookdeck.com) | ✓✓ | ✓✓ | ✓ | ✓ | $0 / $39 |
| [Catchhook](https://catchhook.app) | ✓✓ | ✓ | ✓ `@catchhook/tunnel` | ✓ | $10/mois |
| [HookReplay](https://devhunt.org/tool/hookreplay) | ✓ | ✓ | ✓ CLI | — | — |
| Svix Play | ✓ | limité | — | — | gratuit |
| ngrok | inspect | basique | ✓✓ | — | freemium |
| Stripe CLI | — | ✓ | ✓ | — | gratuit (Stripe only) |

## Concurrents directs à surveiller

### HookReplay

- Domaines : `hookreplay.com`, `hookreplay.dev`
- Tagline : *Capture once. Replay forever.*
- npm + CLI + guides Stripe
- **Risque** : nom quasi identique à « Replayhook »

### Catchhook

- Produit mature : temp endpoints, tunnel, replay, AI summaries
- `npx @catchhook/tunnel`
- Plans dès $10/mois, 14-day trial

### webhook.site

- Standard du marché, gratuit pour tester
- Payant : replay, historique 100k, workflows no-code
- CLI forward vers localhost

## Positionnement Hookscope

```
                    no-code / workflows
                           ↑
              webhook.site ●
                           |
    dev / CLI ←────────────┼────────────→ infra prod
         Hookscope ●       |           ● Hookdeck
         Stripe CLI ●      |
                           ↓
                      debug local
```

### Wedge vs concurrence

| Concurrent | Leur force | Angle Hookscope |
|------------|------------|-----------------|
| webhook.site | Feature-rich, no-code | Un seul job : replay loop + response view |
| Catchhook | Tunnel + AI | CLI-first, minimal, dev pur |
| HookReplay | Même idée, déjà lancé | UX replay/response supérieure |
| Hookdeck | Prod infra | Ne pas concurrencer — rester debug |

## Ce qui ne suffit pas pour gagner

- « Encore une URL qui reçoit des POST »
- Feature parity avec webhook.site
- Prix plus bas sans différenciation UX

## Ce qui peut gagner

- Time-to-value < 2 min (`npx @hookscope/cli inbox`)
- Vue split **request ↔ response** comme écran principal
- Playbooks provider (Stripe, GitHub) — V1
- CI fixtures — V2
- Open source CLI — confiance + distribution