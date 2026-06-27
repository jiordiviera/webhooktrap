# Summary

## Settings refactor — scroll-spy → sections tabulaires + intégration profile

**Problème**: La page Settings scrollait toutes les sections (scroll-spy avec IntersectionObserver). Pas assez lisible, mélangeait `profile` et `settings`.

**Changement d'architecture :**
- Scroll-spy et IntersectionObserver retirés
- Navigation par section **active unique** (une section visible à la fois)
- URL hash (`#account`, `#security`, `#notifications`) pour navigation directe
- Sidebar gauche sticky pour basculer entre sections

**Route `/profile` supprimée :**
- Le contenu de ProfilePage a été intégré dans la section **Account** des Settings
- `features/profile/components/profile-page.tsx` supprimé
- `app/(workspace)/profile/page.tsx` supprimé
- Le dropdown utilisateur pointe maintenant vers `/settings#account`

**Account section** (nouveau) :
- Avatar upload (via `useMediaUpload`, accepte jpg/png/webp)
- Formulaire display name (react-hook-form + zod, validation `profileSchema`)
- Email (readonly), Member since, User ID
- Messages de succès/erreur inline

**Fichiers modifiés :**
| Fichier | Action |
|---------|--------|
| `apps/web/features/settings/components/settings-page.tsx` | Réécrit — sections tabulaires, contenu profile intégré |
| `apps/web/app/components/dashboard/nav-user.tsx` | Lien `/profile` → `/settings#account` |
| `apps/web/app/components/dashboard/breadcrumb.tsx` | Cas `/profile` supprimé |
| `apps/web/app/(workspace)/profile/page.tsx` | Supprimé |
| `apps/web/features/profile/` | Supprimé |
