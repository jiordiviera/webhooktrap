# QA verification: slice 1 (identity + guest sessions) and slice 2 (workspace and projects)

Verifier: A11. Date: 2026-11-15. Branch `phase5/foundation`.
Scope: spec coverage, full automated run, exploratory browser testing on `https://webhooktrap.test`, accessibility spot-check on four pages. No app code or tests were edited and nothing was committed. Only this file was written.

## Verdicts

| Slice | Verdict | Why |
| ----- | ------- | --- |
| 1 Identity + guest sessions | **PASS WITH ISSUES** | Backend is thorough and green. Every flow works end to end in a real browser (register, verify, sign in/out, TOTP set-up and challenge, forgot/reset, deep-link `next`). No blocker. One Medium that breaks a core 2FA path (F-01), plus focus, redirect and flash gaps. |
| 2 Workspace and projects | **PASS WITH ISSUES** | Create, rename, retention, delete, switcher, guest Danger page and uniform 404 all work, single requests on double click, no console errors. Gaps are UI polish and accessibility (F-02, F-05, F-06, F-09, F-12), no data or ownership defects. |

Environment note: partway through the session A06's slice 3 work in progress left the dev site returning HTTP 500 on every page for about 15 minutes (see F-15). All automated checks ran on a clean tree before that. Browser checks after that point ran against a tree that included uncommitted slice 3 shell changes (project switcher, tabs and capture chip appeared in the header during the session). Where a finding depends on that, it is marked.

## Findings

Severity: High = blocks a core path or leaks data; Medium = a spec'd behaviour or a core path is wrong or silent; Low = polish, edge case or minor spec deviation; Info = not a defect in the slice.

| ID | Sev | Slice | Area | Steps to reproduce | Expected | Actual |
| -- | --- | ----- | ---- | ------------------ | -------- | ------ |
| F-01 | Medium | 1 | S-17A authenticator set-up | Signed in, `/account/security` > Set up > Continue > type `000000` > Turn on | Dialog stays open, field cleared and focused, an inline error ("That code didn't work…"), as `SecurityDialogs` `onError` intends | Server answers `errors.code = code_invalid`, but the dialog closes silently. The secret is shown once and is gone, so the user must start over. No error text is shown anywhere on the page. Reproduced twice. |
| F-02 | Medium | 1, 2 | Dialog focus return (A11Y §9 "returned to the opener on close") | (a) Security > Change password > Esc. (b) Project settings > Danger > Delete project > Esc or Cancel | Focus returns to the button that opened the dialog | Focus lands on `<body>` in both. The project switcher menu does return focus correctly. Dialogs also lack `aria-modal` (A11Y §9 names it). |
| F-03 | Medium | 1 | Redirects after verify and sign-in (S-25, S-02) | Register, open the verify link in the same browser, press Confirm. Separately: sign in with no `next` | Verify goes to `next` or `/projects/new` with flash "Email confirmed."; signed-in visit goes to the last project | Both land on `/` (the marketing landing, with a "Dashboard" button). `VerifyEmailController` redirects to `/`; `SignInController` falls back to `/`. |
| F-04 | Medium | 1, 2 | Flash not rendered on landing | End a guest session; or confirm an email. Watch `[role=status]` and page text for 3 s | "Session ended. Nothing was kept." (`copy.guest.ended`, S-16, S-18) and "Email confirmed." | The server sets the flash (`session_ended`, `email_verified`), the landing page shows nothing. Flash works on app pages (rename, retention, delete toasts all show). |
| F-05 | Medium | 2 | Guest "End session now" on the capture page (S-16 Guest) | As a guest, on `/p/{key}` press **End session now** in the top bar | Confirm first: "End this session? Every delivery, run and share link is deleted now." | The top-bar button runs `router.post(guest.end)` at once, no dialog, and deletes the session. The same action on Settings > Danger does confirm. Check again after slice 3 replaces the shell. |
| F-06 | Medium | 2 | Not-found page (S-18) | Signed in or out, open `/p/nonexistent1`, a deleted project, or someone else's project | Branded bare-shell page, h1 "Not found", "This page doesn't exist or isn't shared with you.", one action (S-18) | Framework default: white page, "404 \| Not Found", no h1, no link, ignores the dark theme. No `resources/views/errors/` and no Inertia error page; 403, 419 and 500 are very likely the same (404 only was observed). |
| F-07 | Low | 1 | Empty submit on code forms (known open item in progress.md) | TOTP set-up dialog and the 2FA challenge: press Verify / Turn on with an empty field | An error next to the field and a summary (A11Y G17, §9) | Only moves focus to the field, no text. (The forgot-password, register and sign-in forms do show messages.) |
| F-08 | Low | 1 | S-25 check-inbox copy | Register, land on `/verify-email` | "We sent a link to {email}. …" | "We sent a link to your address." The address is not shown. |
| F-09 | Low | 2 | Long unbroken project name at 390 px | Create a project named `<i>Wide</i> & "q" W…W123` (about 65 chars, no spaces, allowed ≤ 80). Open `/p/{key}`, `/projects`, settings, Danger at 390 | No page-level horizontal scroll (A11Y G8) | Page overflows: stream 481, projects 534, settings 445 (breadcrumb), Danger 498 CSS px at a 390 viewport. Names with spaces wrap fine. HTML in names is escaped correctly. |
| F-10 | Low | 1 | `/account/delete` at 390 and 320; `/p/{key}/settings/budgets` at 320 | Open at 390 / 320 | No horizontal scroll | Delete: the account side nav is 530 px wide, overflow 156 px (390) and 226 px (320). Budgets overflow 42 px at 320. |
| F-11 | Low | 1 | Double submit, TOTP "Turn on" | Double-click Turn on with a valid code | One request | Two `POST /account/security/totp/confirm`. The second is rejected as a reused code, so no harm done (10 of 10 recovery codes left), but the button is not disabled while in flight. Project create, rename, retention, project delete, guest end and forgot-password each sent one request on a double click. The server has no idempotency guard on `POST /projects/new`. |
| F-12 | Low | 2 | Form errors and focus on S-15 and S-16 | New project with 81 chars; or rename to empty | Focus to the first invalid field; summary `role="alert"` once (A11Y §9 Settings) | Field error is associated (`aria-invalid`, `aria-describedby`) but focus stays on the button after a server error (client-side empty check does focus the field), and there is no summary alert. After **Save retention** focus drops to `<body>`. Register and sign-in do put a summary with links to the fields, so S-15/S-16 are inconsistent with them. |
| F-13 | Low | 1, 2 | Target size (G7) | Measure on any app page | ≥ 24 px; ≥ 44 px on touch | Logo link 20 × 20; "Forgot password?" 101 × 16, "Create an account" and "Set a password…" 15 px high (inline-text exception may apply to the last two). Touch (390) 44 px not checked element by element. |
| F-14 | Low | 2 | Settings layout on mobile (S-16) | Open `/p/{key}/settings` at 390 | "List page then pages" | A wrapped tab row above the page. Works; differs from the spec. |
| F-15 | Info | 3 (WIP) | Dev site 500 during slice 3 | Any page while A06 had `ProjectCaptureUrls` unbound | n/a | `BindingResolutionException: Target [App\Modules\Workspace\Contracts\ProjectCaptureUrls] is not instantiable` on every page using shared props, for 15+ minutes. Caused by uncommitted slice 3 work (staged deletion of `NoCaptureUrls.php`, `WorkspaceSharedProps`). Not a slice 1 or 2 defect, but a shared checkout is not a stable test target. Recovered by itself. |
| F-16 | Info | 2 | Signed-in capture page at committed HEAD | Sign in, create a project, land on `/p/{key}` | S-00 shell: switcher, account menu, settings | The committed `Stream.tsx` has only the logo and the name, so a signed-in user has no visible way to settings, the project list or sign-out from that page. The slice 3 shell (uncommitted) adds them. Re-verify in slice 3. |
| F-17 | Info | 2 | Contract text | `contracts.md` "Slice 2 notes" says a guest on an account-only route gets the uniform not-found | n/a | Code and test (`ProjectsTest` "refuses /projects to guests and anonymous visitors") redirect guests to `/sign-in`. The tested behaviour is reasonable (S-02 has a guest note), so correct the contract text. |

## Spec coverage

Legend: Impl = implemented and seen working in the browser; Test = a PHP test fails if the server side breaks; UI test = any test of the React screen. There are no UI tests of any kind (no Pest Browser, Vitest or Playwright in the repo), so every "UI" column is empty.

### Contract routes (all 69 present in `route:list`)

| Area | Impl | Test | Gap |
| ---- | ---- | ---- | --- |
| Register, verify (GET never verifies, POST does), resend limit | yes | `RegistrationAndVerificationTest` | Redirect target after confirm not asserted (F-03). Copy of check-inbox not asserted (F-08). |
| Sign in, uniform failure, throttles, next, sign out, session revoke | yes | `SignInTest`, `UiFollowUps` (next) | None server-side. |
| 2FA challenge, TOTP once, recovery code, attempts | yes | `TwoFactorTest`, `UiFollowUps` | UI of "attempts left ≤ 2", paused state, "Cancel and sign out" not rendered under test. |
| TOTP enrolment and confirm | yes | `AccountSecurityTest` (happy path) | Wrong-code path untested at HTTP-redirect level and in UI (F-01). |
| Forgot / reset, 60 min, single use, revokes sessions, 1.x import | yes | `PasswordResetTest` | None server-side. |
| Account recovery, email change, deletion, confirm-auth, OAuth, WebAuthn | yes (not walked in browser except confirm-auth bounce) | `AccountRecoveryTest`, `EmailChangeTest`, `AccountDeletionTest`, `OAuthTest`, `WebAuthn*Test`, `SecurityReviewFixesTest`, `DeltaReviewFixesTest` | Passkey and OAuth flows were not exercised in a browser (no virtual authenticator, no provider). |
| Guest `POST /new`, expiry, `/expired`, `POST /session/end`, claim | yes | `GuestSessionTest`, `ClaimTest` | Flash after end not tested (F-04). |
| `GET /projects` (0/1/2+), `/projects/new`, `POST /projects/new` | yes | `ProjectsTest`, `OwnershipTest` | Double-submit guard none (F-11). |
| `/p/{key}/settings[/section]`, `PATCH name`, `PATCH retention`, `DELETE /p/{key}` | yes | `ProjectsTest`, `OwnershipTest` | Flash codes asserted on the server only; no test that the front-end copy map covers them (F-04 class). |
| Ownership, uniform 404, agent/runner principals, guest Danger only | yes | `OwnershipTest` (strong) | None. |
| Counters, quotas, account-deletion cascade | yes | `CountersTest`, `AccountDeletionCascadeTest` | Not walked in browser (no UI yet). |

### Screens

| Screen | Impl | Server test | UI test | Notes |
| ------ | ---- | ----------- | ------- | ----- |
| S-02 Sign in | yes | yes | none | Copy, order, `autocomplete="username webauthn"`, wrong-password clear+focus, Show toggle OK. |
| S-24 Create account | yes | yes | none | Summary alert with links to fields OK. |
| S-25 Verify email | yes | yes | none | F-03, F-08. |
| S-26 Forgot / reset | yes | yes | none | Uniform answer OK; reset names the account; reused link shows "This link doesn't work". |
| S-27 Two-step challenge | yes | yes | none | One field, `one-time-code`, "Other ways", error copy OK. F-07. |
| S-28 Confirm it's you | yes (bounce seen) | yes | none | Not walked fully. |
| S-29, S-30, S-31, S-32 | yes (not walked) | yes | none | Link pages exist; reused reset link correct. |
| S-17 Account, S-17A Security, S-17B dialogs | yes | yes | none | 7 `h2`, table headers, "Sign out Chrome on Linux" names OK. F-01, F-02, F-10. |
| S-18 System pages | **partial**: Expired and the session-ended path exist; Not found, Server error, Delivery not found do not (F-06) | `GuestSessionTest` for `/expired` | none | |
| S-00 shell + banner | partial (slice 3 is replacing it) | `OwnershipTest` (props) | none | Switcher: menu button, Esc, arrow keys, focus return all OK. Guest meter shows; "1 h left" and quota states not seen. |
| S-03 waiting | yes (guest meter, status line) | `GuestSessionTest` partly | none | F-05, F-16. |
| S-15 Projects, New project | yes | yes | none | 0/1/2+ redirects verified. F-09, F-12. |
| S-16 Settings (Capture, CI, Budgets, Danger, guest) | yes | `OwnershipTest` props only | none | Rotate URL, CI tokens, budgets content wait for later slices (placeholders shown). F-14. |

## Test-gap list (a regression here would not fail any test)

1. **No UI tests at all.** Nothing renders a React page: `tests/Browser` does not exist, no Vitest. All of F-01 to F-14 shipped with 305 green tests. A first Pest Browser or Playwright suite for sign-in, TOTP enrol, project create/rename/delete and the delete dialog would cover most of them.
2. TOTP confirm with a wrong code: dialog stays open and shows an error (F-01).
3. Verify-email success lands on `next` or `/projects/new` with flash; sign-in with no `next` lands where the spec says (F-03).
4. Every server flash code has a copy case, and the landing page renders `flash` (F-04). A table-driven test over `Answers::flash` codes against `copy.ts` would catch missing ones.
5. Branded system pages for 404, 403, 419, 500 (S-18), including the dark theme (F-06).
6. Dialog focus: initial focus on title, trapped, returned to opener on close (F-02). No A11Y AX-26 automation exists.
7. Automated axe run (AX-01) on sign-in, register, security, projects, settings in both themes at 1440 and 390 is not in CI. (I did not run axe-core; see commands.)
8. Reflow at 390 and 320 with long names and on `/account/delete` (F-09, F-10).
9. Double submit: client button disabled while in flight on every mutating form; server idempotency on `POST /projects/new` (F-11).
10. S-27 rendering: "Cancel and sign out", attempts-left line when ≤ 2, paused state, security-key panel. Server counters are tested, the display is not.
11. Guest meter states (1 h left, quota) and the guest "End session now" confirm (F-05).
12. Settings pages `ci` and `budgets` are only checked for "opens with props".
13. Browser-level passkey (CDP virtual authenticator, AX-24) and OAuth round trips.
14. The 100-entry/100k common-password list is exercised by policy tests; the UI "Show" toggle `aria-pressed` and name are not.

## Accessibility spot-check (A11Y §2, §9; sign-in, security, projects, settings)

Method: scripted DOM audit in headless Chrome at 1280 and 390, keyboard tab-through, error-state inspection. axe-core was not run.

| Check | Sign-in | Security | Projects | Settings |
| ----- | ------- | -------- | -------- | -------- |
| `lang="en"`, one `main`, one `h1`, descriptive `<title>` | pass | pass | pass | pass |
| Landmarks | main, aside (no header or nav, bare shell) | header, main, nav "Account" | header, main | header, nav "Main", main, nav "Project settings", nav "Main" (bottom) |
| Heading outline | h1 only | h1 + 7 × h2 | h1 + h2 "2 projects" | h1 + 3 × h2 |
| Skip link is first focusable | yes | yes | yes | yes (focus goes to `h1` on load per G3, so Tab skips it; Shift+Tab reaches it) |
| Every control has a name; no placeholder-only fields | pass | pass | pass | pass |
| Tab order equals visual order, no trap | pass | pass | pass | pass (dialogs trap correctly) |
| Visible focus ring | pass (pink ring seen in screenshots) | pass | pass | pass |
| Error association (`aria-invalid`, `aria-describedby`) | pass; summary `role="alert"` with field links | pass for dialogs not exercised on wrong code (F-01) | pass for name | pass for name |
| Error summary on S-15/S-16 forms | n/a | n/a | missing (F-12) | missing (F-12) |
| Contrast (text, computed) | 0 failures of 4.5:1 / 3:1 on all four pages | same | same | same |
| Target size ≥ 24 px | links 15 to 16 px | logo 20 px | logo 20 px | logo 20 px |
| Reflow 390 / 320 | pass | pass | overflow with long name (F-09) | overflow with long name (F-09); budgets at 320 (F-10) |
| Dialog: title focused on open, trapped, focus returned | not applicable | title focus yes, return no (F-02) | not applicable | same as Security |
| Project switcher | not applicable | not applicable | Enter opens, arrows move, Esc closes and returns focus, `aria-haspopup="menu"`, `aria-expanded` updates | same |

Not checked: forced colours, text spacing, reduced motion, screen reader, light theme, mobile touch target size per element, G6 focus-not-obscured.

## Commands run and results

All on 2026-11-15, working tree clean at the start (HEAD `ba8af2b`, later `aa4cc1e`; docs-only commits in between).

| Command | Result |
| ------- | ------ |
| `git status --short`, `git log --oneline` | Clean at the start. Later uncommitted slice 3 changes appeared (middleware, shell components, routes, `app/Modules/Capture/`), so the shared checkout moved under the browser session. |
| `php artisan test` | **305 passed, 3111 assertions, 49.6 s**. Exit 0. |
| `vendor/bin/phpstan analyse --no-progress` | **0 errors**. Exit 0. |
| `pnpm run check` (`vp check`) | **Pass**: 140 files formatted, no lint warnings in 104 files. Exit 0. |
| `pnpm run types:check` (`tsc --noEmit`) | **Pass**. Exit 0. |
| No failures, so no attribution to slice 3 work was needed for the automated checks. | |
| Browser runs: playwright-core 1.x from `/tmp/pw`, `/usr/bin/google-chrome` headless, `ignoreHTTPSErrors` for the lerd certificate, viewports 1280 × 800/900, 390 × 844, 320 × 800. Mail read from `storage/logs/laravel.log` (`MAIL_MAILER=log`). | |
| Flows run | Register (empty, invalid, valid), resend, verify link page and confirm, wrong and right password, TOTP set-up and confirm, recovery codes dialog, sign out, 2FA challenge (empty, wrong, right), forgot password (empty, unknown, real), reset (weak, valid, reused link), deep-link `next`, create/rename/retention/delete project, switcher, guest create via `/expired`, guest Danger page, guest end session, uniform 404, mobile overflow sweep. |
| Console | No console errors or warnings on any page except the three deliberate 404 responses and one 403 on a fake verify link. |
| axe-core | I downloaded `axe.min.js` from jsdelivr into the scratchpad before deciding not to use it without your approval. It was not run or injected. Delete `…/scratchpad/qa-s12/axe.min.js` if unwanted. |

Local test data left in the dev database (all `example.test`, none are real accounts): user `qa1@example.test` (password and TOTP enrolled, secret and recovery codes in the scratchpad `qa1.secret.json`), its projects "QA Alpha Renamed" and the wide-name one, soft-deleted "Bravo Project" and "Charlie", several expired or ended guest sessions. Safe to purge.

Screenshots: `/tmp/claude-1000/-home-jiordiviera-workspace-personal-webhooktrap/60a4e179-4b01-4f20-b695-9f15b11a8dc5/scratchpad/qa-s12/` (for example `totp-wrong.png`, `404.png`, `delete-dialog.png`, `m-settings.png`, `m-delete-account.png`, `signout.png`, `a11y-*.png`).

## Recommendation

Slices 1 and 2 can close once F-01 is fixed (Medium, core 2FA set-up). F-02 to F-06 should be scheduled before the Phase 5 gate; F-04 and F-05 may be solved by the slice 3 shell, so re-verify them there. The rest can be tracked as Low. Add a UI test layer (gap 1) before slice 4, otherwise these classes of defect keep passing CI.
