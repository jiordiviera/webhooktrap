# Parcours & décisions

Chronologie des décisions prises durant le brainstorming.

## 1. Migration Laravel → AdonisJS (PulseSend API)

**Question initiale** : migrer l'API Laravel existante vers AdonisJS ?

**Conclusion** :
- Faisable mais c'est une **réécriture**, pas une migration mécanique
- Points bloquants : Filament, Passport OAuth2, écosystème Spatie, event sourcing
- Migration partielle (SDK v1 seulement) recommandée si on gardait Laravel

## 2. Greenfield — repartir de zéro

**Décision** : refaire le projet sans tenir compte du code existant.

**Conditions acceptées** :
- MVP simple (6 semaines), pas parity feature avec Laravel
- Stack 100 % TypeScript (AdonisJS + Next.js monorepo)
- Reporter : event sourcing, 2FA, social auth, Filament
- Conserver : specs produit, schéma domaine, leçons apprises

**PulseSend v2 initial** (abandonné) :
- API transactionnelle email
- AdonisJS + Next.js + PostgreSQL (pas de Docker, pas de Redis au MVP)
- MVP : auth, tenant, API keys, send email, tracking

## 3. Analyse business PulseSend (intellectual sparring)

**Verdict** :
- Marché email transactionnel = **red ocean**
- Moat délivrabilité difficile à construire
- Architecture technique ≠ innovation business perçue
- Greenfield PulseSend viable comme studio/portfolio, pas comme différenciation claire

## 4. Pivot vers outil dev — Replayhook

**Idée** : debugger webhooks (capture + replay + response view)

**Pourquoi mieux que PulseSend** :
- Pain plus aigu et immédiat
- Pas de moat SMTP
- MVP 3–4 semaines
- Recycle ~50 % des patterns (multi-tenant, API keys, events, dashboard)

## 5. Naming — Hookscope retenu

**Replayhook** abandonné comme nom principal :
- Trop proche de **HookReplay** (concurrent direct)
- `replayhook.com` déjà pris (fév. 2026)

**Hookscope** recommandé :
- Distinct, explicite (scope + hook)
- `hookscope.dev` probablement disponible
- Tagline : *Inspect. Replay. See the response.*

## 6. Greenfield total

- Repo **`hookscope/`** uniquement — **aucun** code repris de `pulse-send` ou `pulsesend-api`
- Nouveau monorepo, nouveau design (branding Imagine + Cormorant)

## 7. Positionnement régional retiré

Toute référence « Afrique-first » / XAF / latence régionale retirée du positionnement.
Produit cible : **marché global**, développeurs backend.

## Décisions ouvertes

| # | Question | Statut |
|---|----------|--------|
| 1 | Registrar `hookscope.dev` | À faire |
| 2 | Replay localhost (limitation vs tunnel V1) | Tunnel en V2 |
| 3 | Inbox anonyme auto sur landing | Recommandé Oui |
| 4 | SMTP provider (si besoin futur) | N/A pour Hookscope |