<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Events\LegacyAccountActivated;
use Illuminate\Support\Facades\DB;

/**
 * First proof that the account address is owned (domain §2.4 pre-hijack
 * rule, A09 F1). Marks the email verified. Unless the proof came from the
 * browser that registered the account (the verification link confirmed
 * there), the account is reset to a clean state: every credential set before
 * verification (password, TOTP, WebAuthn credentials, recovery codes, linked
 * identities) is discarded and every session is revoked. Imported 1.x
 * accounts are always reset.
 */
final readonly class EmailOwnership
{
    public function __construct(private Clock $clock, private SessionRevoker $revoker) {}

    /**
     * @param  string  $via  link|reset|oauth|password_set_link
     * @param  bool  $byRegistrant  the verification link was confirmed in the registering browser
     * @return bool whether pre-verification credentials were discarded
     */
    public function proven(User $user, string $via, bool $byRegistrant = false): bool
    {
        if ($user->email_verified_at !== null) {
            return false;
        }
        $discard = $user->legacy_source !== null || ! ($byRegistrant && $via === 'link');
        $activated = $user->legacy_source !== null && $user->legacy_activated_at === null && in_array($via, ['reset', 'oauth'], true);

        DB::transaction(function () use ($user, $discard, $activated): void {
            $attributes = ['email_verified_at' => $this->clock->now()];
            if ($discard) {
                $attributes['password_hash'] = null;
                $attributes['two_factor_enabled_at'] = null;
                $user->webauthnCredentials()->delete();
                $user->totp()->delete();
                $user->recoveryCodes()->delete();
                $user->identities()->delete();
                $this->revoker->revokeAll($user->id, 'email_verified');
            }
            if ($activated) {
                $attributes['legacy_activated_at'] = $this->clock->now();
            }
            $user->forceFill($attributes)->save();
        });

        EmailVerified::dispatch($user->id, $via);
        if ($activated) {
            LegacyAccountActivated::dispatch($user->id, $via);
        }

        return $discard;
    }
}
