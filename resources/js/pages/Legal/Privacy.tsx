import { LegalPage, LegalSection } from '@/components/legal/legal-page';
import { terms } from '@/routes';

/*
 * DRAFT for the PO to review. Not legal advice; not reviewed by a lawyer.
 * Facts come from config/workspace.php, config/retention.php,
 * project/architecture/schema.md §8 and §2 and domain-model.md §2.10, §12,
 * project/operations/hosting-options.md HD-4 and decisions-log.md.
 *
 * Open points for the PO:
 * - TODO(PO): confirm the contact address privacy@webhooktrap.dev exists.
 * - TODO(PO): confirm the Contabo region (EU, Germany) of the production VPS.
 * - TODO(PO): no self-service data export exists yet; export is on request.
 * - TODO(PO): confirm the minimum age (16), the 30-day answer time and the
 *   email notice for significant policy changes; they are commitments.
 */

// TODO(PO): confirm this mailbox exists before the page is linked from the OAuth apps.
const CONTACT = 'privacy@webhooktrap.dev';

export default function Privacy() {
    return (
        <LegalPage
            title="Privacy policy"
            updated="2026-11-16"
            lede={
                <p>
                    This policy covers the hosted Webhook Trap service at
                    app.webhooktrap.dev. If you run Webhook Trap on your own
                    server, your data stays with you and this policy does not
                    apply: the operator of that instance is responsible for it.
                </p>
            }
        >
            <LegalSection id="who" title="1. Who runs Webhook Trap">
                <p>
                    Webhook Trap is an open-source project (AGPL-3.0) run by
                    Jiordi Kengne, who is the data controller for the hosted
                    service.
                </p>
                <p>
                    Questions, requests about your data and complaints:{' '}
                    <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
                </p>
            </LegalSection>

            <LegalSection id="collected" title="2. What we collect">
                <h3>Account data</h3>
                <ul>
                    <li>
                        Your email address and, if you give one, a display name.
                    </li>
                    <li>
                        Your password, if you set one, stored only as an
                        argon2id hash.
                    </li>
                    <li>
                        If you sign in with GitHub or Google: the account id the
                        provider gives us, whether it says your email is
                        verified, and your provider login or email to show you
                        which account is linked. We ask only for your email
                        address and basic profile. We get no access to your
                        repositories, contacts or files, and we do not store
                        your avatar.
                    </li>
                </ul>

                <h3>Passkeys and two-step sign-in</h3>
                <ul>
                    <li>
                        Passkeys and security keys: the public key, the
                        credential id, the name you give it and when it was last
                        used. The private key never leaves your device.
                    </li>
                    <li>
                        Authenticator apps: the shared secret, stored encrypted.
                    </li>
                    <li>Recovery codes, stored only as hashes.</li>
                </ul>

                <h3>Captured webhook requests</h3>
                <p>
                    When a provider (or anyone) sends a request to one of your
                    capture URLs, we store it so you can inspect and replay it:
                    method, path and query, headers, the body (up to 5 MiB;
                    above that, only its size and hash), and the source IP
                    address. The values of <code>Authorization</code>,{' '}
                    <code>Proxy-Authorization</code> and <code>Cookie</code>{' '}
                    headers are replaced by a placeholder before storage. What
                    the body contains depends on what your provider sends; it
                    may include personal data about your own customers. You
                    decide what you point at a capture URL.
                </p>

                <h3>Run results</h3>
                <p>
                    When you prove a handler against provider scenarios, we
                    store the run plan, the deliveries, the outcomes and the
                    findings. Your provider signing secrets and raw probe values
                    stay on the machine running the agent; the server stores
                    only display forms of results.
                </p>

                <h3>Guest sessions</h3>
                <p>
                    You can try Webhook Trap without an account. A guest session
                    is a random token in a cookie, stored only as a hash, with
                    the captures and runs made in it. It lasts 24 hours.
                </p>

                <h3>Security log</h3>
                <p>
                    We record security events on your account, such as sign-ins,
                    failed attempts, password changes and added or removed keys,
                    with the time, a short browser label (for example
                    &quot;Firefox on Linux&quot;) and a shortened network
                    address (IPv4 /24 or IPv6 /48; the full address is not
                    kept). You can see this log in your account settings.
                </p>
            </LegalSection>

            <LegalSection id="use" title="3. How we use it">
                <ul>
                    <li>To sign you in and keep your account secure.</li>
                    <li>
                        To store, show and replay the webhooks you capture, and
                        to run the scenarios you start.
                    </li>
                    <li>
                        To send you account emails: confirming your address,
                        resetting a password, security alerts, and confirming an
                        account deletion.
                    </li>
                    <li>To enforce rate limits and quotas and stop abuse.</li>
                </ul>
                <p>
                    The legal bases are the contract with you (providing the
                    service you signed up for) and our legitimate interest in
                    keeping it secure.
                </p>
                <p>
                    <strong>
                        We do not sell your data, show ads, or use third-party
                        analytics or trackers.
                    </strong>{' '}
                    The app loads no analytics scripts.
                </p>
            </LegalSection>

            <LegalSection id="retention" title="4. How long we keep it">
                <ul>
                    <li>
                        <strong>Captures:</strong> per project, you choose 1, 7,
                        14 or 30 days (30 by default). Older captures are
                        deleted daily.
                    </li>
                    <li>
                        <strong>Runs:</strong> per project, you choose 7, 30 or
                        90 days (90 by default).
                    </li>
                    <li>
                        <strong>Deleted projects:</strong> erased for good 24
                        hours after you delete them.
                    </li>
                    <li>
                        <strong>Guest sessions:</strong> 24 hours from creation,
                        then deleted with everything in them, unless you move
                        them into an account when you sign up.
                    </li>
                    <li>
                        <strong>Security log:</strong> 1 year.
                    </li>
                    <li>
                        <strong>Your account:</strong> until you delete it.
                    </li>
                </ul>

                <h3>Deleting your account</h3>
                <p>
                    You can delete your account in your settings. We ask you to
                    confirm it: with a second factor if you use one, otherwise
                    through a link sent to your email address. The account is
                    closed at once and erased from the live database within 1
                    hour: projects, captures, runs, keys, sessions and linked
                    sign-in methods. Security log entries about the account are
                    kept for the rest of their year with the email address
                    masked.
                </p>
                <p>
                    We keep a short deletion record (an internal account id and
                    the time of deletion, never your email address) so a restore
                    from backup can never bring a deleted account back.
                </p>

                <h3>Backups</h3>
                <p>
                    Backups are encrypted and kept for 14 days (point-in-time
                    recovery covers the last 7). Deleted data leaves the backups
                    within 14 days.
                </p>
            </LegalSection>

            <LegalSection
                id="where"
                title="5. Where it is stored and who processes it"
            >
                <ul>
                    <li>
                        {/* TODO(PO): confirm the Contabo region of the production VPS. */}
                        <strong>Contabo</strong>: the server running the app and
                        database, in the EU (Germany).
                    </li>
                    <li>
                        <strong>Cloudflare</strong>: DNS for webhooktrap.dev,
                        and R2 storage for encrypted backups.
                    </li>
                    <li>
                        <strong>PulseSend</strong> (delivering through Amazon
                        SES): sends account emails. It receives your email
                        address and the message.
                    </li>
                    <li>
                        <strong>GitHub and Google</strong>: only if you choose
                        to sign in with them. Their own privacy policies apply
                        to what you share with them.
                    </li>
                </ul>
                <p>
                    We share data with no one else, except when the law requires
                    it.
                </p>
            </LegalSection>

            <LegalSection id="cookies" title="6. Cookies">
                <p>
                    We use only cookies the service needs. No tracking cookies.
                </p>
                <ul>
                    <li>
                        <code>__Host-wt_session</code>: your signed-in session
                        (encrypted).
                    </li>
                    <li>
                        <code>__Host-XSRF-TOKEN</code>: protects forms against
                        cross-site request forgery.
                    </li>
                    <li>
                        <code>__Host-wt_guest</code>: set only when you start a
                        guest session.
                    </li>
                    <li>
                        <code>appearance</code>, <code>layout</code>,{' '}
                        <code>layout_sidebar</code>, <code>sidebar_state</code>:
                        set only when you change the theme or layout, to
                        remember your choice on this device.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection id="rights" title="7. Your rights">
                <p>
                    Under the GDPR and similar laws you can ask to access,
                    correct, export or delete your data, object to or restrict
                    its use, and complain to your data protection authority.
                </p>
                <ul>
                    <li>
                        Access and correction: your account settings show your
                        email, sign-in methods, keys, sessions and security log,
                        and let you change them.
                    </li>
                    <li>
                        Deletion: delete captures, projects or your whole
                        account yourself at any time.
                    </li>
                    <li>
                        Export and anything else: write to{' '}
                        <a href={`mailto:${CONTACT}`}>{CONTACT}</a>. We answer
                        within 30 days.
                    </li>
                </ul>
            </LegalSection>

            <LegalSection id="children" title="8. Children">
                <p>
                    Webhook Trap is a developer tool and is not meant for anyone
                    under 16. We do not knowingly collect data from children. If
                    you think a child has an account, write to us and we will
                    delete it.
                </p>
            </LegalSection>

            <LegalSection id="security" title="9. Security">
                <p>
                    All traffic uses HTTPS. Passwords and recovery codes are
                    hashed, authenticator secrets are encrypted, and backups are
                    encrypted. No system is perfectly secure; if we learn of a
                    breach that affects your data, we will tell you without
                    undue delay.
                </p>
            </LegalSection>

            <LegalSection id="changes" title="10. Changes to this policy">
                <p>
                    We will update the date above when this policy changes. For
                    significant changes we will tell account holders by email
                    before they take effect. See also the{' '}
                    <a href={terms().url}>Terms of service</a>.
                </p>
            </LegalSection>
        </LegalPage>
    );
}
