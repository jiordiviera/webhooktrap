# Vision — Hookvane

## En une phrase

**Hookvane** aide les développeurs à débugger les webhooks : recevoir n'importe quel événement (Stripe, GitHub, Shopify…), rejouer le payload vers leur app, et voir la réponse de leur serveur.

## Mission

Plateforme de **debug webhook** orientée développeurs — intégration en quelques minutes, flux receive → replay → response visible.

## Le problème

1. L'événement arrive quand `localhost` est éteint
2. Le payload n'est pas facilement inspectable
3. Retester = copier JSON à la main ou re-déclencher côté provider
4. La **réponse** de l'app (200, 500, timeout) n'est pas visible au même endroit

## La solution

```
Provider → Hookvane inbox → Inspect → Replay → Voir la response
```

### Flux utilisateur

1. Obtenir une URL : `https://hookscope.dev/i/abc123`
2. Coller dans les settings webhook du provider
3. Voir l'événement en temps réel
4. Rejouer vers `http://localhost:7777/webhooks` (ou staging)
5. Voir status code, headers, body et latence de la réponse

## Wedge produit

> **Le Stripe CLI pour tous les webhooks** — pas juste voir le payload, mais rejouer et voir ce que ton app répond.

## Cible

| Persona | Besoin |
|---------|--------|
| **Dev backend solo** | Intègre Stripe ce soir, teste vite |
| **Petite équipe** | Partager un webhook reçu à 23h |
| **Dev en debug** | Rejouer un event exact sans re-simuler |

**Hors cible** : équipes ops cherchant infra webhook prod (retries, SLA) → Hookdeck / Svix.

## Ce que Hookvane est / n'est pas

| Est | N'est pas |
|-----|-----------|
| Debugger webhook dev-first | Plateforme email (PulseSend) |
| Inbox + replay + vue response | Infra production complète |
| CLI + dashboard | Outil no-code / workflows |
| SaaS dev tool | Marketing automation |

## Critère de succès MVP

Un dev externe complète le flow Stripe webhook **sans aide en < 15 minutes**.

## Métriques long terme

| Horizon | KPI |
|---------|-----|
| MVP | Quickstart < 15 min |
| V1 | 10 tenants actifs, usage récurrent |
| V2 | Rétention équipe, fixtures CI |