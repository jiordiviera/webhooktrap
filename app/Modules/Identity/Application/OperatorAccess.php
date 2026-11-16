<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\Request;

/**
 * The operator flag (decision 74). Set and cleared only from the host
 * console (`operator:grant` / `operator:revoke`); no HTTP route and no mass
 * assignment reaches it. Both changes are audited.
 */
final readonly class OperatorAccess
{
    public const string GRANTED = 'granted';

    public const string REVOKED = 'revoked';

    public const string UNCHANGED = 'unchanged';

    public const string NOT_FOUND = 'not_found';

    public function __construct(private Audit $audit) {}

    public function grant(string $email): string
    {
        return $this->set($email, true);
    }

    public function revoke(string $email): string
    {
        return $this->set($email, false);
    }

    /** Whether the account may still enter the panel (it may have lost a factor since). */
    public function stillEligible(string $email): bool
    {
        $user = $this->find($email);

        return $user !== null && $user->isEligibleOperator();
    }

    /** Audits one admin page view: who, which page, when (security log, `audit_log`). */
    public function pageViewed(Request $request, string $userId, string $page): void
    {
        $user = User::query()->find($userId);
        $this->audit->record('operator_page_viewed', $user, RequestContext::from($request), ['page' => $page]);
    }

    private function set(string $email, bool $operator): string
    {
        $user = $this->find($email);
        if ($user === null) {
            return self::NOT_FOUND;
        }
        if ($user->is_operator === $operator) {
            return self::UNCHANGED;
        }
        $user->forceFill(['is_operator' => $operator])->save();
        $this->audit->record($operator ? 'operator_granted' : 'operator_revoked', $user, RequestContext::console(), actorUserId: null);

        return $operator ? self::GRANTED : self::REVOKED;
    }

    private function find(string $email): ?User
    {
        return User::query()->where('email', trim($email))->whereNull('deleted_at')->first();
    }
}
