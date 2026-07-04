import type { Metadata } from "next";
import { Bricolage_Grotesque, JetBrains_Mono } from "next/font/google";
import { Providers } from "@/app/components/providers";
import "@workspace/ui/globals.css";
import { cn } from "@workspace/ui/lib/utils";
import { siteUrl, productName } from "@/lib/config";

const bricolageGrotesque = Bricolage_Grotesque({
  subsets: ["latin"],
  variable: "--font-sans",
  display: "swap",
});

const jetbrainsMono = JetBrains_Mono({
  subsets: ["latin"],
  variable: "--font-mono",
});

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: `${productName} — Webhook debugger`,
    template: `%s — ${productName}`,
  },
  description:
    "Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.",
  openGraph: {
    type: "website",
    siteName: productName,
    title: `${productName} — Webhook debugger`,
    description:
      "Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.",
    images: [
      {
        url: "/og-image.jpg",
        width: 1200,
        height: 630,
        alt: `${productName} — Webhook debugging, simplified.`,
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: `${productName} — Webhook debugger`,
    description:
      "Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.",
    images: ["/og-image.jpg"],
  },
  robots: { index: true, follow: true },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={cn(
        bricolageGrotesque.variable,
        jetbrainsMono.variable,
        "font-sans"
      )}
      suppressHydrationWarning
    >
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
