<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\AuditLog;

/**
 * Identity's audit rows. For security-log codes (schema §7 allow-list) the
 * display params go in `after.params`; nothing else of the row is shown.
 */
final readonly class Audit
{
    public function __construct(private AuditLog $log) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function record(string $action, ?User $user, ?RequestContext $context = null, array $params = [], ?string $actorUserId = null, ?string $emailHmac = null): void
    {
        $this->log->record(
            action: $action,
            subjectUserId: $user?->id,
            subject: $user?->publicId(),
            actorUserId: $actorUserId ?? $user?->id,
            ipPrefix: $context === null || $context->addressKey === 'console' ? null : $context->ipPrefix,
            uaLabel: $context?->uaLabel,
            after: $params === [] ? null : ['params' => $params],
            emailHmac: $emailHmac,
        );
    }
}
