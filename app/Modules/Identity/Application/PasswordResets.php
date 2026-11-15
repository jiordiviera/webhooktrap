<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\Tokens;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\PasswordReset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password" (contracts §3.4): uniform answer; the link is
 * `/reset-password/{token}` with no email parameter, 60 min, single use,
 * replaced by a newer request; the reset verifies the email, revokes every
 * session and never counts as a second factor or recent authentication (AR-2).
 */
final readonly class PasswordResets
{
    public function __construct(
        private Clock $clock,
        private SignInThrottle $throttle,
        private Notifier $notifier,
        private Audit $audit,
        private SessionRevoker $revoker,
        private EmailOwnership $ownership,
        private AccountRecovery $recovery,
    ) {}

    /** Returns the time to show when the visible per-address limit is hit. */
    public function request(string $email, RequestContext $context): ?CarbonImmutable
    {
        $c = config('identity.throttle');
        if ($retryAt = $this->throttle->limit("forgot:addr:{$context->addressKey}", $c['forgot_address']['max'], $c['forgot_address']['decay'])) {
            return $retryAt;
        }
        // Per-email limit: silently dropped, the answer stays uniform.
        if ($this->throttle->limit('forgot:email:'.$this->throttle->emailKey($email), $c['forgot_email']['max'], $c['forgot_email']['decay']) !== null) {
            return null;
        }
        $user = User::query()->where('email', trim($email))->first();
        if ($user === null || ! $user->isLive()) {
            return null;
        }

        $token = Tokens::random();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => bin2hex(Tokens::hash($token)),
            'created_at' => $this->clock->now(),
        ]);
        $this->notifier->send($user->email, 'password-reset', [], url('/reset-password/'.$token));

        return null;
    }

    /** The user a reset token belongs to while it is valid; consumes nothing. */
    public function resolve(string $token): ?User
    {
        $row = DB::table('password_reset_tokens')->where('token', bin2hex(Tokens::hash($token)))->first();
        if ($row === null) {
            return null;
        }
        $created = CarbonImmutable::parse((string) $row->created_at);
        if ($created->addMinutes((int) config('identity.links.reset_minutes'))->lessThanOrEqualTo($this->clock->now())) {
            return null;
        }
        $user = User::query()->where('email', $row->email)->first();

        return $user !== null && $user->isLive() ? $user : null;
    }

    public function reset(string $token, string $password, RequestContext $context): User
    {
        $user = $this->resolve($token);
        if ($user === null) {
            throw new IdentityRefusal('link_invalid');
        }
        if ($error = PasswordPolicy::fromConfig()->violation($password, $user->email)) {
            throw ValidationException::withMessages(['password' => $error]);
        }

        DB::transaction(function () use ($user, $password): void {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $this->ownership->proven($user, 'reset');
            $user->refresh()->forceFill(['password_hash' => Hash::make($password)])->save();
            $this->revoker->revokeAll($user->id, 'password_reset');
        });

        $this->audit->record('password_reset', $user, $context);
        $this->notifier->send($user->email, 'password-reset-done');
        PasswordReset::dispatch($user->id);
        $this->recovery->extendOnReset($user, $context);

        return $user->refresh();
    }
}
