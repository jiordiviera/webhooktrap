<?php

declare(strict_types=1);

/*
 * The list of bounded contexts (domain-model.md §1). Adding a module means
 * adding it here, so the cross-module rules below cover it.
 */
function webhooktrapModules(): array
{
    return [
        'Identity', 'Workspace', 'Capture', 'Agent', 'Proving',
        'Packs', 'Fixtures', 'Sharing', 'Instance', 'Retention',
    ];
}

/*
 * Namespaces of a module that other modules must not touch. Only
 * Application (services), Contracts (interfaces, read models) and Events
 * (domain events) are public (domain-model.md §1.2 rules 1 and 2).
 */
function webhooktrapPrivateLayers(): array
{
    return ['Domain', 'Http', 'Jobs', 'Listeners', 'Console', 'Database'];
}
