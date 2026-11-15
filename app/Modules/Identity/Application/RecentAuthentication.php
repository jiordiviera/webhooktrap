<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\SessionKeys;
use Illuminate\Http\Request;

/**
 * Recent authentication ("sudo", 10 min) and purpose-bound confirmations
 * (DL-1: `delete_account`, 5 min, single use), kept in the server session
 * with the method that set them (domain §2.1).
 */
final readonly class RecentAuthentication
{
    public const array SECOND_FACTOR_METHODS = ['totp', 'webauthn', 'passkey', 'security_key'];

    public function __construct(private Clock $clock) {}

    public function record(Request $request, string $method): void
    {
        $request->session()->put(SessionKeys::RECENT_AUTH, [
            'at' => $this->clock->now()->getTimestamp(),
            'method' => $method,
            'second_factor' => in_array($method, self::SECOND_FACTOR_METHODS, true),
        ]);
    }

    public function recordConfirmation(Request $request, string $purpose, string $method): void
    {
        $this->record($request, $method);
        $request->session()->put(SessionKeys::CONFIRMATION, [
            'purpose' => $purpose,
            'method' => $method,
            'second_factor' => in_array($method, self::SECOND_FACTOR_METHODS, true),
            'at' => $this->clock->now()->getTimestamp(),
            'consumed' => false,
        ]);
    }

    public function isRecent(Request $request, bool $requireSecondFactor = false): bool
    {
        $recent = $request->session()->get(SessionKeys::RECENT_AUTH);
        if (! is_array($recent) || ! isset($recent['at'])) {
            return false;
        }
        if ($this->clock->now()->getTimestamp() - (int) $recent['at'] > (int) config('identity.recent_auth_seconds')) {
            return false;
        }

        return ! $requireSecondFactor || ($recent['second_factor'] ?? false) === true;
    }

    /**
     * State of the `delete_account` confirmation: `ok`, or the refusal code
     * `confirmation_stale` / `second_factor_required` (contracts §3.4).
     */
    public function deleteConfirmationState(Request $request, bool $requireSecondFactor): string
    {
        $c = $request->session()->get(SessionKeys::CONFIRMATION);
        if (! is_array($c) || ($c['purpose'] ?? null) !== 'delete_account' || ($c['consumed'] ?? true) === true
            || $this->clock->now()->getTimestamp() - (int) ($c['at'] ?? 0) > (int) config('identity.delete_confirmation_seconds')) {
            return 'confirmation_stale';
        }
        if ($requireSecondFactor && ($c['second_factor'] ?? false) !== true) {
            return 'second_factor_required';
        }

        return 'ok';
    }

    public function consumeConfirmation(Request $request): void
    {
        $c = $request->session()->get(SessionKeys::CONFIRMATION);
        if (is_array($c)) {
            $c['consumed'] = true;
            $request->session()->put(SessionKeys::CONFIRMATION, $c);
        }
    }

    public function forget(Request $request): void
    {
        $request->session()->forget([SessionKeys::RECENT_AUTH, SessionKeys::CONFIRMATION]);
    }
}
