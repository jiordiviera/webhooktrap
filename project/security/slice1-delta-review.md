# A09 security review · Slice 1 delta (identity UI and follow-ups)

**Date:** 2026-11-15 · **Reviewer:** A09 · **Branch:** `phase5/foundation`
**Range read:** `git diff 743935c..HEAD -- app routes resources/js config tests`. This covers commits `540bff0`, `d3b7829` and `9554ab9` (A07 identity UI, WebAuthn and QR helpers), `4bc655e` and `012ceca` (A06 follow-ups), and `9ea8f44` and `1c11dab` (A07 restyle and the shared form-validation hook).
**Read against:** the previous review (`slice1-review.md`, F1 to F12), the threat model (TM-T4, TM-T11, WA-*), and contracts §3.4.
**Suite:** `php artisan test` reported 273 passed with 2693 assertions.

Exploit detail is kept in the private notes, not here.

## Verdict

**SHIP WITH FIXES.** The delta has no High finding and does not reopen F1 to F12. One Medium finding (D1) should be fixed before public sign-up. It concerns one-time secrets that stay in the browser after the dialog closes and after sign-out. The other findings are Low or Info.

## What is right

- **Open redirect (`next`).** `safeNext` now refuses absolute and scheme URLs, `//host`, any backslash, any control or whitespace byte (which blocks tab and newline smuggling), and anything over 2000 bytes. Password sign-in validates `next` and passes it through `safeNext`. It stores `next` as the intended URL only on `SIGNED_IN` or `CHALLENGE`, never on a failure or a throttle. The intended URL survives the 2FA challenge (the session is migrated, not flushed) and is consumed by `redirect()->intended()`, which stays on the app origin. The OAuth confirmation retry URL runs `next` through `safeNext` again and allow-lists `action` and `reason`. Tests cover 11 bypass shapes.
- **OAuth error paths.** The session flow is pulled before anything else, so it is single use. A provider `error=access_denied` maps to `oauth_cancelled`, and any other error or exchange failure maps to `oauth_failed`. Both return before any account work. `recordConfirmation` (recent auth) is reached only after the OAuth-only check and the provider-subject match. An `IdentityRefusal` in any purpose becomes an error code and grants nothing. Tests assert that `recent_auth` stays empty after both a cancel and a mismatch. A provider that answers but cannot sign the person in still gets the uniform `invalid_credentials`.
- **`Inertia::location` on `POST /confirm-auth/{provider}`.** The target is the URL Socialite builds on the server, with the state stored in the session. Nothing from the request reaches the host. The route still sits behind `auth:web` and the OAuth-only check.
- **S-28 props.** `reason` is a code from the `ConfirmReasons` allow-list, and `verb` is looked up on the server, so a crafted link cannot put its own text on the page. `providerLabels` appears only on an authenticated page and only for the owner's own OAuth-only account.
- **Unauthenticated props.** `oauthProvider` is limited by the route pattern to `github` or `google`. `attemptsLeft` and the challenge `email` are shown only to a browser that passed a first factor. `recoveryPending` appears only for the signed-in principal and only for a confirmed request. Reset and verify link pages name the account only to someone who holds a valid emailed token (see D4). The forgot-password, register and sign-in answers did not change, so nothing new enumerates accounts.
- **CSRF on link pages.** `csrfToken` goes only to the seven emailed-link pages, which are `no-store` and `no-referrer`, and a test checks that ordinary pages don't get it. The native `POST` form posts the session token, and the routes still sit behind the CSRF middleware. F4 holds on the client: both the Inertia client and the WebAuthn `fetch` read `__Host-XSRF-TOKEN`.
- **WebAuthn client.** It uses native `navigator.credentials` with no library. The challenge, `rpId`, user verification and `allowCredentials` all come from the server's options and are only decoded on the client. The client assumes no origin and no RP ID, and the server checks both. A ceremony starts only from a button, or from conditional UI on S-02, and the server decides the mode. `allowCredentials` is filled only for the signed-in user (`confirm`) or a browser with a pending 2FA challenge. A first-factor-less caller gets discoverable-only options (empty list) behind a per-address throttle. The sign-in redirect comes from the server's intended URL.
- **TOTP QR.** The encoder is local (`lib/identity/qr.ts`): it makes no network request, uses no third-party service or canvas export, and logs nothing. The secret reaches the page only as the `totpSetup` flash prop on the redirect after `startTotp`, and the dialog drops it when it closes. The S-28 round trip stores only the dialog kind and non-secret choices in `sessionStorage`.
- **XSS.** No `resources/js` file uses `dangerouslySetInnerHTML` or `innerHTML`. Server strings are rendered as React text. The only `href` built from server data is S-28's Cancel link, and its value has already passed `safeNext`.
- **F1 to F12** still hold. The delta does not touch their code paths, apart from additive, read-only methods in `SecondFactorChallenge` and `SecurityViews`. Each fix has a test in `SecurityReviewFixesTest.php`, and all of them pass.

## Findings

| ID | Severity | Area | Finding | Fix |
| -- | -------- | ---- | ------- | --- |
| D1 | **Medium** | Client secrets | **One-time secrets persist in browser history.** Inertia history encryption is off: `inertia.history.encrypt` is not set and defaults to `false` through the env. Nothing calls `clearHistory` on sign-out, and `/account/security` is not `no-store`. Every page's props therefore stay in the tab's history state after navigation and after sign-out. That includes the TOTP setup secret and `otpauth://` URI, freshly generated recovery codes, email addresses, and the session and IP list. Closing the dialog does not remove them, despite "shown once". F11 protects the server copy, but this client copy is unprotected | Turn on history encryption in `config/inertia.php`, set in config and not overridable by env. Call `Inertia::clearHistory()` on sign-out, on the end of a browser session (tombstone), and on guest-session end. Send `Cache-Control: no-store` on `/account/security`, `/two-factor` and `/confirm-auth`. Add a test that the Security response carries the encrypt-history flag |
| D2 | Low | Redirects | **`next` can point at a GET that starts a state change.** `safeNext` accepts any same-origin path. `GET /account/security/identities/{provider}/connect` starts an OAuth link flow, and a fresh sign-in satisfies its recent-auth check. A crafted sign-in link can therefore send the person straight into a link round trip after they sign in. The GET itself predates this delta and was already reachable by a top-level cross-site navigation while recent auth was fresh | Make connect a `POST` that answers with `Inertia::location`, like `/confirm-auth/{provider}`. Optionally, refuse `next` values that point at identity mutation paths |
| D3 | Low | Props | **The two-step challenge shows the full account email.** S-27 now receives `email` unmasked. When the first factor was an OAuth sign-in through a linked identity whose provider email differs from the account's, the page reveals the account address to someone who has proven only the provider account | Use `EmailMask` for this prop, or show the provider label instead when the first factor was OAuth |
| D4 | Info | Props | The reset page (`email`, `legacy`) and the verify-link page (`email`) name the account to anyone holding a valid link. This is acceptable because the link already proves the mailbox, and both pages are `no-store` and `no-referrer`. Keep the email off the invalid-link state, as the code does now | None required |
| D5 | Info | OAuth | The callback acts on `error=` before any `state` comparison. A cross-site navigation can end a person's in-flight OAuth flow, which changes nothing and grants nothing. This is a nuisance, not a bypass | Optional: honour `error` only when `state` matches the stored flow |
| D6 | Info | WebAuthn UX | Conditional UI requests passkey options on every S-02 load. Each request counts toward the per-address `passkey_options` throttle and replaces any ceremony state in the session. People behind shared NAT may hit the limit sooner | Keep an eye on the limit; consider a separate, larger bucket for conditional requests |

## Required before public sign-up

1. **D1.** Encrypt history, clear it on sign-out, and set `no-store` on the security pages.

## Recommended before go-live

D2 (connect as `POST`) and D3 (mask the email on S-27). D5 and D6 are optional.
