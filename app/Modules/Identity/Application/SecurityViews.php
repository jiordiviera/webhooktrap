<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\EmailMask;
use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthn\AssertionPolicy;
use App\Modules\Identity\Domain\WebAuthn\RelyingParty;
use App\Modules\Identity\Domain\WebAuthnCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Builds the account-security props (contracts §3.4 `SecurityView`,
 * `AccountDeletionView`, `RecoveryReview`) and the security log read model
 * (schema §7 allow-list; only the display params are shown).
 */
final readonly class SecurityViews
{
    public const array LOG_CODES = [
        'signed_in', 'sign_in_held', 'second_factor_hold', 'key_added', 'key_removed', 'key_blocked', 'key_unblocked',
        'totp_enabled', 'totp_disabled', 'two_factor_disabled', 'recovery_codes_regenerated', 'password_set',
        'password_changed', 'password_reset', 'email_change_requested', 'email_change_cancelled', 'email_change_confirmed',
        'account_deletion_requested', 'account_deletion_cancelled', 'email_changed', 'identity_linked', 'identity_unlinked',
        'session_revoked', 'recovery_requested', 'recovery_confirmed', 'recovery_extended', 'recovery_cancelled',
        'recovery_completed', 'run_token_created',
    ];

    /** @param  iterable<AccountDeletionParticipant>  $participants */
    public function __construct(
        private Clock $clock,
        private EmailChanges $emailChanges,
        private AccountRecovery $recovery,
        private TwoFactorState $twoFactor,
        private AccountDeletion $deletion,
        private iterable $participants = [],
    ) {}

    /** @return array<string, mixed> */
    public function security(User $user, ?string $currentSessionId): array
    {
        $pending = $this->emailChanges->pending($user);
        $open = $this->recovery->open($user);
        $lock = $this->recovery->lock($user);
        $review = $this->recovery->reviewable($user);
        $totp = $user->totp()->first();

        return array_filter([
            'hasPassword' => $user->hasPassword(),
            'email' => $user->email,
            'emailVerified' => $user->isVerified(),
            'pendingEmail' => $pending === null ? null : [
                'masked' => EmailMask::mask($pending->new_email),
                'expiresAt' => self::iso($pending->expires_at),
                'awaiting' => $pending->old_required
                    ? ($pending->new_confirmed_at === null && $pending->old_confirmed_at === null ? 'both' : ($pending->new_confirmed_at === null ? 'new' : 'old'))
                    : 'new',
            ],
            'identities' => $user->identities()->orderBy('provider')->get()->map(fn (UserIdentity $i): array => [
                'provider' => $i->provider, 'label' => $i->provider_label, 'linkedAt' => self::iso($i->linked_at),
            ])->all(),
            'keys' => $user->webauthnCredentials()->orderBy('created_at')->get()->map(fn (WebAuthnCredential $k): array => $this->keyRow($k))->all(),
            'totp' => ['enabled' => $totp?->confirmed_at !== null, 'since' => self::iso($totp?->confirmed_at)],
            'recoveryCodesLeft' => $this->twoFactor->codesLeft($user),
            'twoFactor' => $user->two_factor_enabled_at !== null,
            'recovery' => self::recoveryPending($open),
            'lock' => $lock === null ? null : ['until' => self::iso($lock['until']), 'reason' => $lock['reason']],
            'recoveryReview' => $review === null ? null : ['until' => self::iso($review->lock_until)],
            'sessions' => $this->sessions($user, $currentSessionId),
            'securityLog' => $this->log($user, $this->clock->now()->subDays(30)),
            'passkeysAvailable' => $this->passkeysAvailable(),
            'maxKeys' => (int) config('identity.webauthn.max_credentials'),
            'passwordPolicy' => PasswordPolicy::fromConfig()->toProp(),
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> contracts §3.4 SecurityKeyRow */
    public function keyRow(WebAuthnCredential $k): array
    {
        $row = [
            'id' => $k->publicId(),
            'name' => $k->name,
            'kind' => AssertionPolicy::isPasskey($k) ? 'passkey' : 'security_key',
            'synced' => $k->backup_eligible ? $k->backup_state : null,
            'createdAt' => self::iso($k->created_at),
            'lastUsedAt' => self::iso($k->last_used_at),
            'flagged' => $k->flagged_at !== null,
        ];
        if ($k->flagged_at !== null) {
            $row += ['flaggedAt' => self::iso($k->flagged_at), 'flagCause' => $k->flag_cause, 'canUnblock' => $k->flag_count < 2];
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function sessions(User $user, ?string $currentSessionId): array
    {
        $now = $this->clock->now();
        $rows = UserSession::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->where('idle_expires_at', '>', $now)->where('absolute_expires_at', '>', $now)
            ->orderByDesc('last_seen_at')->get();

        return array_values($rows->sortBy(fn (UserSession $s): int => $s->id === $currentSessionId ? 0 : 1)->values()
            ->map(fn (UserSession $s): array => [
                'id' => $s->publicId(),
                'current' => $s->id === $currentSessionId,
                'browser' => $s->ua_label,
                'network' => $s->ip_prefix,
                'signedInAt' => self::iso($s->created_at),
                'lastSeenAt' => self::iso($s->last_seen_at),
                'method' => ['first' => $s->first_factor, 'second' => $s->second_factor],
            ])->all());
    }

    /** @return list<array<string, mixed>> */
    public function log(User $user, ?CarbonImmutable $since = null, int $limit = 50): array
    {
        return array_values(DB::table('audit_log')
            ->where('subject_user_id', $user->id)
            ->whereIn('action', self::LOG_CODES)
            ->when($since !== null, fn ($q) => $q->where('at', '>=', $since))
            ->orderByDesc('at')->limit($limit)
            ->get(['action', 'at', 'ua_label', 'ip_prefix', 'after'])
            ->map(function (object $row): array {
                $after = is_string($row->after) ? json_decode($row->after, true) : null;
                $entry = [
                    'at' => self::iso(CarbonImmutable::parse((string) $row->at)),
                    'action' => $row->action,
                    'browser' => $row->ua_label,
                    'network' => $row->ip_prefix,
                ];
                if (is_array($after) && isset($after['params']) && is_array($after['params'])) {
                    $entry['params'] = $after['params'];
                }

                return $entry;
            })->all());
    }

    /** @return array<string, mixed> */
    public function deletion(User $user): array
    {
        $pending = $this->deletion->pending($user);
        $summary = [];
        foreach ($this->participants as $participant) {
            $summary += $participant->summary($user->id);
        }

        return array_filter([
            'mode' => $user->hasSecondFactor() ? 'immediate' : 'email_link',
            'pending' => $pending === null ? null : ['expiresAt' => self::iso($pending->expires_at)],
            'email' => $user->email,
            // TODO(capture/agents/tokens slices): real counts; deferred props.
            'projects' => $summary['projects'] ?? [], 'runs' => 0, 'agents' => [], 'ciTokens' => 0, 'personalTokens' => 0, 'activeRuns' => 0,
            'pendingEmail' => $this->emailChanges->pending($user) !== null,
            'blocked' => $this->deletion->blocked($user),
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, mixed>|null */
    public function review(User $user): ?array
    {
        $request = $this->recovery->reviewable($user);
        if ($request === null || $request->completed_at === null) {
            return null;
        }

        return [
            'completedAt' => self::iso($request->completed_at),
            'lockUntil' => self::iso($request->lock_until),
            'agents' => [],   // TODO(agent slice): agents set to needs_reconfirm (RC-4)
            'ciTokens' => [], // TODO(tokens slice): the user's CI tokens across projects
            'log' => $this->log($user, $request->requested_at->subMinute()),
        ];
    }

    public function passkeysAvailable(): bool
    {
        return RelyingParty::fromConfig()->available();
    }

    /**
     * The S-00 banner and the Security page (U-5): a confirmed, open
     * recovery with when it was asked for and when it completes; `extended`
     * marks the RC-2 seven-day wait after a password reset by email.
     *
     * @return array{requestedAt: string|null, completesAt: string|null, extended: bool}|null
     */
    public static function recoveryPending(?AccountRecoveryRequest $open): ?array
    {
        if ($open?->confirmed_at === null || $open->completes_at === null) {
            return null;
        }

        return [
            'requestedAt' => self::iso($open->requested_at),
            'completesAt' => self::iso($open->completes_at),
            'extended' => $open->wait_extended_at !== null,
        ];
    }

    public static function iso(?CarbonImmutable $at): ?string
    {
        return $at?->utc()->toIso8601ZuluString('millisecond');
    }
}
