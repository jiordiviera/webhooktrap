/*
 * S-16 Budgets: read-only provider budgets from RD `providers`
 * (project/design/realistic-data.md, provider-budgets.md verified
 * 2026-11-14). Static until the Packs slice serves them; a `budget:`
 * override from webhooktrap.yml arrives with the agent (BUD-2).
 */
export type ProviderBudget = {
    provider: 'stripe' | 'shopify' | 'github' | 'slack' | 'paddle' | 'standard';
    name: string;
    label: string;
    source: string;
    tolerance: string;
};

export const providerBudgets: ProviderBudget[] = [
    {
        provider: 'stripe',
        name: 'Stripe',
        label: '10 s budget · Webhook Trap default',
        source: 'default',
        tolerance: '300 s',
    },
    {
        provider: 'shopify',
        name: 'Shopify',
        label: '5 s · Shopify limit',
        source: 'Shopify docs',
        tolerance: 'none',
    },
    {
        provider: 'github',
        name: 'GitHub',
        label: '10 s · GitHub limit',
        source: 'GitHub docs',
        tolerance: 'none',
    },
    {
        provider: 'slack',
        name: 'Slack',
        label: '3 s · Slack limit',
        source: 'Slack docs',
        tolerance: '300 s',
    },
    {
        provider: 'paddle',
        name: 'Paddle',
        label: '5 s · Paddle limit',
        source: 'Paddle docs',
        tolerance: '5 s',
    },
    {
        provider: 'standard',
        name: 'Standard Webhooks',
        label: '10 s budget · Webhook Trap default',
        source: 'default',
        tolerance: '300 s',
    },
];

/** Names only, no budget, until their pack ships (A-18). */
export const comingProviders = ['Twilio', 'Adyen'];
