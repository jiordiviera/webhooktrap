# Analyse business

## Modèle économique

| Plan | Prix | Inclus |
|------|------|--------|
| **Free** | $0 | 1 inbox anon / 3 avec compte, volume limité, retention courte |
| **Dev** | $12/mois | 5 inboxes, 5k events, 7j retention |
| **Team** | $29/mois | 20 inboxes, 50k events, 30j, 5 users, partage |
| **Pro** | $79/mois | Volume élevé, CI fixtures, rétention longue |

### Unit economics

- Coût marginal par event : stockage JSON + replay HTTP → **très faible**
- Pas de SMTP, pas de délivrabilité
- Infra initiale : ~$0–25/mois (API + PostgreSQL Neon free / petit plan)

### Projections réalistes (solo, 12 mois)

| Métrique | Objectif |
|----------|----------|
| Users free | 500–2000 |
| Payants | 30–80 |
| MRR | $500–1500 |
| Plafond solo | ~$5–10k MRR |

## Pourquoi ce business tient mieux que PulseSend

| Critère | PulseSend | Webhook Trap |
|---------|-----------|-----------|
| Concurrence | Red ocean (Resend, SendGrid…) | Serré mais niche debug |
| Moat infra | Délivrabilité email | Faible — wedge = DX |
| Time-to-MVP | 3–6 mois | 3–4 semaines |
| Marges | Fines (SMTP) | Élevées |
| Churn risk | Modéré | Élevé si usage ponctuel |

## Risques

| Risque | Sévérité | Mitigation |
|--------|----------|------------|
| webhook.site / Catchhook / HookReplay | Élevé | CLI-first, replay+response UX, playbooks |
| Usage ponctuel → churn | Élevé | Historique équipe, partage, CI V2 |
| Replay vers localhost depuis cloud | Moyen | Documenter ; tunnel natif V2 |
| Marché trop petit | Moyen | Bon side project / studio |

## Options de projet (choix implicite)

| Option | Description |
|--------|-------------|
| **A — Business** | SaaS dev tool, MRR, distribution |
| **B — Studio** | Portfolio, crédibilité, 10–50 users |
| **C — Infra perso** | Outil interne pour tes propres apps |

Webhook Trap convient à **A** ou **B**, pas besoin de levée de fonds.

## Test validation (48h avant code)

1. 5 devs : « Comment tu debug tes webhooks ? »
2. Mockup replay loop
3. « Tu paierais $12/mois vs webhook.site gratuit ? »
4. Si 0/5 ne voient la différence → affiner le wedge