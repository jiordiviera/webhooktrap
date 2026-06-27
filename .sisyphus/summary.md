# Summary

## API build - Fixé

**Problème**: Build cassé sur 3 fichiers, 8 erreurs TypeScript.

**Fichiers modifiés:**

1. **`apps/api/app/support/paginated_response.ts`** — `paginatedPayload` attendait `rows: unknown[]`, mais `InboxTransformer.transform()` retourne un `Collection` (descripteur, pas un array). `serialize()` sait résoudre ces descripteurs à runtime. Fix: `rows: unknown[]` → `rows: unknown`.

2. **`apps/api/app/controllers/inboxes_controller.ts`** — Retiré `.toArray()` (inexistant sur `Collection`). Les 2 appels à `paginatedPayload` passent maintenant directement les descripteurs `Collection`.

3. **Schema (`database/schema.ts`)** — Régénéré par le codegen du build. Les colonnes `twoFactorSecret` et `twoFactorRecoveryCodes` ont maintenant les bons types (`TwoFactorSecret | null`, `string[]`) avec `consume`/`prepare` pour le chiffrement.

**Aucun changement** dans `two_factor_auth_controller.ts` — le code original est correct une fois le schema à jour.
