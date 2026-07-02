import type { Metadata } from 'next'
import { productName } from '@/lib/config'
import { Navigation } from "@/components/landing/navigation";
import { HeroSection } from "@/components/landing/hero-section";
import { FeaturesSection } from "@/components/landing/features-section";
import { HowItWorksSection } from "@/components/landing/how-it-works-section";
import { InfrastructureSection } from "@/components/landing/infrastructure-section";
import { MetricsSection } from "@/components/landing/metrics-section";
import { IntegrationsSection } from "@/components/landing/integrations-section";
import { SecuritySection } from "@/components/landing/security-section";
import { DevelopersSection } from "@/components/landing/developers-section";
import { TestimonialsSection } from "@/components/landing/testimonials-section";
import { PricingSection } from "@/components/landing/pricing-section";
import { CtaSection } from "@/components/landing/cta-section";
import { FooterSection } from "@/components/landing/footer-section";
import "@/styles/landing-v2.css";

export const metadata: Metadata = {
  title: `${productName} — Webhook debugger`,
  description:
    'Catch every webhook. Inspect headers and body, replay to localhost, share read-only links. Developer-first debugging without the tunnel maze.',
  openGraph: {
    title: `${productName} — Webhook debugger`,
    description:
      'Catch every webhook. Inspect headers and body, replay to localhost, share read-only links. Developer-first debugging without the tunnel maze.',
  },
  twitter: {
    title: `${productName} — Webhook debugger`,
    description:
      'Catch every webhook. Inspect headers and body, replay to localhost, share read-only links. Developer-first debugging without the tunnel maze.',
  },
}

const jsonLd = {
  '@context': 'https://schema.org',
  '@type': 'WebApplication',
  name: productName,
  url: 'https://hookscope.dev',
  description:
    'Webhook debugging tool. Receive, inspect, replay, and share webhook payloads.',
  applicationCategory: 'DeveloperApplication',
  operatingSystem: 'All',
  author: {
    '@type': 'Person',
    name: 'Jiordi Viera',
  },
  offers: {
    '@type': 'Offer',
    price: '0',
    priceCurrency: 'USD',
  },
}

export default function Home() {
  return (
    <main className="relative min-h-screen overflow-x-hidden noise-overlay">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <Navigation />
      <HeroSection />
      <FeaturesSection />
      <HowItWorksSection />
      <InfrastructureSection />
      <MetricsSection />
      <IntegrationsSection />
      <SecuritySection />
      <DevelopersSection />
      <TestimonialsSection />
      <PricingSection />
      <CtaSection />
      <FooterSection />
    </main>
  );
}
