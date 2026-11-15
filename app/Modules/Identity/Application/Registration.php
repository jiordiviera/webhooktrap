<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\UserRegistered;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * `POST /register` (contracts §3.4). The answer is the same whether or not
 * the address is taken: both land on the verify-email page and nobody is
 * signed in. A taken (live) address gets the `account-exists` notice; a
 * tombstoned one gets nothing (domain §2.10). The new user is signed in only
 * when the verification link is confirmed in this same browser session.
 */
final readonly class Registration
{
    public function __construct(
        private SignInThrottle $throttle,
        private Notifier $notifier,
        private EmailVerification $verification,
    ) {}

    public function register(Request $request, string $name, string $email, string $password): ?CarbonImmutable
    {
        if (config('identity.instance') === 'selfhost' && config('identity.signup_policy', 'open') !== 'open') {
            throw new IdentityRefusal('signup_closed');
        }
        $context = RequestContext::from($request);
        $c = config('identity.throttle.register_address');
        if ($retryAt = $this->throttle->limit("register:{$context->addressKey}", $c['max'], $c['decay'])) {
            return $retryAt;
        }
        if ($error = PasswordPolicy::fromConfig()->violation($password, $email)) {
            throw ValidationException::withMessages(['password' => $error]);
        }

        $existing = User::query()->where('email', trim($email))->first();
        if ($existing !== null) {
            if ($existing->isLive()) {
                $this->notifier->send($existing->email, 'account-exists', [], url('/sign-in'));
            }
            // Same hash cost as a real sign-up, so timing does not tell the cases apart.
            Hash::make($password);

            return null;
        }

        $user = User::query()->create([
            'email' => trim($email),
            'name' => $name,
            'password_hash' => Hash::make($password),
            'webauthn_user_handle' => random_bytes(32),
        ]);
        UserRegistered::dispatch($user->id, 'password');
        $request->session()->put(SessionKeys::PENDING_REGISTRATION, $user->id);
        $this->verification->send($user);

        return null;
    }
}
