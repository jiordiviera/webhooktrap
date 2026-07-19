import type { Metadata } from "next";
import { Navigation } from "@/components/landing/navigation";
import { FooterSection } from "@/components/landing/footer-section";
import { productName } from "@/lib/config";

export const metadata: Metadata = {
  title: "Privacy",
  description: `How ${productName} handles webhook data, cookies, and analytics.`,
};

export default function PrivacyPage() {
  return (
    <>
      <Navigation />
      <main className="min-h-screen mx-auto max-w-3xl px-6 pt-40 pb-24 lg:px-12">
        <span className="mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
          <span className="h-px w-8 bg-primary/40" />
          Privacy
        </span>
        <h1 className="font-heading mb-10 text-4xl tracking-tight lg:text-6xl">
          What {productName} stores, and why.
        </h1>

        <div className="space-y-10 text-base leading-relaxed text-muted-foreground sm:text-lg">
          <section>
            <h2 className="font-heading mb-3 text-xl text-foreground">
              Webhook payloads
            </h2>
            <p>
              Every request sent to an inbox URL is stored as-is: method,
              headers, body, and query string. The{" "}
              <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">
                authorization
              </code>{" "}
              and{" "}
              <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">
                cookie
              </code>{" "}
              headers are redacted before storage. Anonymous inboxes (no
              account) expire automatically after 48 hours. Inboxes attached
              to an account are kept until you delete them.
            </p>
          </section>

          <section>
            <h2 className="font-heading mb-3 text-xl text-foreground">
              Account & session
            </h2>
            <p>
              If you create an account, we store your email and a hashed
              password. Signing in sets a session cookie used to keep you
              authenticated — this cookie is essential to the product and
              isn&apos;t part of the consent choice below.
            </p>
          </section>

          <section>
            <h2 className="font-heading mb-3 text-xl text-foreground">
              Analytics
            </h2>
            <p>
              We use Vercel Analytics to understand aggregate traffic
              (page views, referrers). It doesn&apos;t use cookies or persist
              a per-visitor identifier, and it only runs after you accept the
              cookie banner. Declining, or not answering, keeps it off.
            </p>
          </section>

          <section>
            <h2 className="font-heading mb-3 text-xl text-foreground">
              Replay
            </h2>
            <p>
              When you replay an event, the stored payload is sent from our
              server to the destination URL you provide. We don&apos;t send
              it anywhere else.
            </p>
          </section>

          <section>
            <h2 className="font-heading mb-3 text-xl text-foreground">
              Questions
            </h2>
            <p>
              Reach out at{" "}
              <a
                href="https://jiordiviera.me"
                target="_blank"
                rel="noopener noreferrer"
                className="underline underline-offset-2 hover:text-foreground"
              >
                jiordiviera.me
              </a>
              .
            </p>
          </section>
        </div>
      </main>
      <FooterSection />
    </>
  );
}
