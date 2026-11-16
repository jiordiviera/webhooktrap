import { LegalPage, LegalSection } from '@/components/legal/legal-page';
import { privacy } from '@/routes';

/*
 * DRAFT for the PO to review. Not legal advice; not reviewed by a lawyer.
 * Quotas come from config/workspace.php (quota.captures_per_day,
 * quota.stored_body_bytes) and are placeholders until the PO sets them.
 *
 * Open points for the PO:
 * - TODO(PO): confirm the contact address privacy@webhooktrap.dev exists.
 * - TODO(PO): governing law and courts (section 9).
 * - TODO(PO): confirm the quota figures (5000 captures/day, 512 MiB).
 * - TODO(PO): confirm the minimum age (16) and the 30-day notice for changes.
 */

// TODO(PO): confirm this mailbox exists before the page is linked from the OAuth apps.
const CONTACT = 'privacy@webhooktrap.dev';

export default function Terms() {
    return (
        <LegalPage
            title="Terms of service"
            updated="2026-11-16"
            lede={
                <p>
                    These terms apply when you use the hosted Webhook Trap
                    service at app.webhooktrap.dev, with or without an account.
                    By using it you agree to them. If you do not agree, do not
                    use the service.
                </p>
            }
        >
            <LegalSection id="service" title="1. The service">
                <p>
                    Webhook Trap captures webhooks sent to your capture URLs,
                    proves a webhook handler against provider scenarios, and
                    replays requests. It is an open-source project run by Jiordi
                    Kengne. The hosted service is free and offered on a
                    best-effort basis; features may change or be removed.
                </p>
                <p>
                    You must be at least 16 to use it. You are responsible for
                    your account and for keeping your sign-in methods safe.
                </p>
            </LegalSection>

            <LegalSection id="use" title="2. Acceptable use">
                <p>You agree not to:</p>
                <ul>
                    <li>
                        send, or point a provider at a capture URL to send,
                        secrets or personal data that you have no right to
                        process. If captures contain personal data about other
                        people, you are responsible for having a lawful basis
                        for it;
                    </li>
                    <li>
                        use capture URLs for anything other than receiving test
                        and development webhooks: no file hosting, no data
                        exfiltration, no command-and-control, no phishing, no
                        flooding;
                    </li>
                    <li>
                        get around rate limits or quotas, for example by
                        creating many accounts or guest sessions;
                    </li>
                    <li>
                        attack, probe or overload the service or other users, or
                        try to access data that is not yours;
                    </li>
                    <li>break the law or anyone else&apos;s rights.</li>
                </ul>
            </LegalSection>

            <LegalSection id="limits" title="3. Limits and quotas">
                <p>The service has limits so it stays fair and available:</p>
                <ul>
                    <li>up to 5,000 captures per project per day;</li>
                    <li>up to 512 MiB of stored request bodies per project;</li>
                    <li>
                        bodies above 5 MiB are not kept, only their size and
                        hash;
                    </li>
                    <li>rate limits on sign-in and other requests.</li>
                </ul>
                <p>
                    Over a quota, a capture URL still answers the sender but
                    stores nothing. We may change these limits.
                </p>
            </LegalSection>

            <LegalSection id="data" title="4. Your data">
                <p>
                    What you capture and create stays yours. You give us
                    permission to store and process it only to run the service
                    for you. Data is deleted according to the retention you
                    choose; see the <a href={privacy().url}>Privacy policy</a>.
                    Keep your own copies of anything you need: the service is
                    not a backup.
                </p>
            </LegalSection>

            <LegalSection id="code" title="5. Open-source code">
                <p>
                    The Webhook Trap source code is licensed under the GNU
                    Affero General Public License v3.0 (AGPL-3.0). These terms
                    cover the hosted service; the AGPL governs your use of the
                    code, including running your own instance. Nothing here
                    limits your rights under the AGPL.
                </p>
            </LegalSection>

            <LegalSection id="suspension" title="6. Suspension and ending">
                <p>
                    We may suspend or close an account, disable capture URLs, or
                    end guest sessions that break these terms or put the service
                    or other users at risk, where possible after telling you
                    why. You can stop using the service and delete your account
                    at any time.
                </p>
            </LegalSection>

            <LegalSection id="warranty" title="7. No warranty">
                <p>
                    The service is provided <strong>&quot;as is&quot;</strong>{' '}
                    and <strong>&quot;as available&quot;</strong>, without
                    warranty of any kind, express or implied, including fitness
                    for a particular purpose, availability and accuracy. A
                    passing run does not guarantee that your handler is correct
                    in production.
                </p>
            </LegalSection>

            <LegalSection id="liability" title="8. Limitation of liability">
                <p>
                    To the extent the law allows, Webhook Trap and its
                    maintainer are not liable for any indirect, incidental or
                    consequential damage, or for lost data, profits or business,
                    arising from your use of the service. Nothing in these terms
                    limits liability that cannot be limited by law.
                </p>
            </LegalSection>

            <LegalSection id="law" title="9. Governing law">
                {/* TODO(PO): governing law and competent courts. */}
                <p>
                    The governing law and competent courts are to be confirmed.
                </p>
            </LegalSection>

            <LegalSection id="changes" title="10. Changes">
                <p>
                    We may update these terms. We will change the date above
                    and, for significant changes, tell account holders by email
                    at least 30 days before they take effect. Using the service
                    after that means you accept the new terms.
                </p>
                <p>
                    Questions: <a href={`mailto:${CONTACT}`}>{CONTACT}</a>.
                </p>
            </LegalSection>
        </LegalPage>
    );
}
