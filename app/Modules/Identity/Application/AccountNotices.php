<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\User;

/**
 * Security notices other modules send to an account (domain §2.10: the
 * address is copied into the queued job). Only live users get one; guests
 * have no address. Templates are A03's codes (e.g. `agent-paired`, AC-7).
 */
final readonly class AccountNotices
{
    public function __construct(private Notifier $notifier) {}

    /** @param  array<string, string|int|null>  $params */
    public function send(string $userId, string $template, array $params = [], ?string $actionUrl = null): bool
    {
        $user = User::query()->whereKey($userId)->whereNull('deleted_at')->whereNull('disabled_at')->first(['id', 'email']);
        if ($user === null || $user->email === '') {
            return false;
        }
        $this->notifier->send($user->email, $template, $params, $actionUrl);

        return true;
    }
}
