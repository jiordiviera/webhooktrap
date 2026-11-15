<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Domain\Mail\IdentityMail;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\RecoveryCodeGenerator;
use App\Modules\Identity\Domain\Totp;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Identity test helpers (slice 1). Passwords are fixture values.
 */

const FIXTURE_PASSWORD = UserFactory::PASSWORD;

function makeUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/** Signs in through the real password route (creates a user_sessions row). */
function signIn(User $user, string $password = FIXTURE_PASSWORD): void
{
    test()->post('/sign-in/password', ['email' => $user->email, 'password' => $password]);
}

/** Forgets this "browser": session data, guards and cookies. */
function newBrowser(): void
{
    app('session')->driver()->flush();
    Auth::forgetGuards();
    test()->flushSession();
}

/** Gives the user a confirmed TOTP factor (and 2FA state); returns the raw secret. */
function enableTotp(User $user): string
{
    $secret = Totp::newSecret();
    TotpFactor::query()->create(['user_id' => $user->id, 'secret' => encrypt($secret), 'confirmed_at' => now()]);
    $user->forceFill(['two_factor_enabled_at' => now()])->save();

    return $secret;
}

/** @return list<string> */
function giveRecoveryCodes(User $user): array
{
    $codes = RecoveryCodeGenerator::generate();
    foreach ($codes as $code) {
        RecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => RecoveryCodeGenerator::hash($code), 'created_at' => now()]);
    }

    return $codes;
}

function totpCode(string $secret, int $stepOffset = 0): string
{
    return Totp::codeAt($secret, Totp::step(now()->getTimestamp()) + $stepOffset);
}

function addKey(User $user, array $attributes = []): WebAuthnCredential
{
    return WebAuthnCredential::query()->create($attributes + [
        'user_id' => $user->id,
        'credential_id' => random_bytes(32),
        'public_key' => random_bytes(64),
        'alg' => -7,
        'sign_count' => 0,
        'discoverable' => true,
        'uv_at_registration' => true,
        'backup_eligible' => true,
        'backup_state' => true,
        'name' => 'Passkey',
    ]);
}

/** @return Collection<int, IdentityMail> */
function queuedMails(string $template, ?string $to = null): Collection
{
    return Mail::queued(IdentityMail::class, fn (IdentityMail $m): bool => $m->template === $template && ($to === null || $m->hasTo($to)));
}

function mailed(string $template, ?string $to = null): bool
{
    return queuedMails($template, $to)->isNotEmpty();
}

/** The path (and query) of the last queued mail with this template. */
function mailedLink(string $template): ?string
{
    $url = queuedMails($template)->last()?->actionUrl;
    if ($url === null) {
        return null;
    }
    $query = parse_url($url, PHP_URL_QUERY);

    return (string) parse_url($url, PHP_URL_PATH).($query ? '?'.$query : '');
}

function auditCount(string $action, ?User $user = null): int
{
    return DB::table('audit_log')->where('action', $action)
        ->when($user !== null, fn ($q) => $q->where('subject_user_id', $user?->id))->count();
}

/**
 * Acts as the user in a fresh browser session with a live user_sessions row
 * and, unless null, a recent authentication by the given method.
 */
function actAs(User $user, ?string $recentMethod = 'password', string $firstFactor = 'password'): UserSession
{
    newBrowser();
    $row = UserSession::query()->create([
        'user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now(),
        'idle_expires_at' => now()->addDays(14), 'absolute_expires_at' => now()->addDays(30),
        'first_factor' => $firstFactor, 'ua_label' => 'Firefox on Linux', 'ip_prefix' => '127.0.0.0/24',
    ]);
    $session = ['identity.user_session_id' => $row->id];
    if ($recentMethod !== null) {
        $session['identity.recent_auth'] = [
            'at' => now()->getTimestamp(), 'method' => $recentMethod,
            'second_factor' => in_array($recentMethod, ['totp', 'webauthn', 'passkey', 'security_key'], true),
        ];
    }
    test()->actingAs(User::query()->findOrFail($user->id), 'web')->withSession($session);

    return $row;
}

/** The OAuth provider returns this profile on the callback. */
function providerReturns(string $provider, string $id, ?string $email, bool $verified = true, string $nickname = 'maya-l'): void
{
    $profile = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
        'id' => $id, 'nickname' => $nickname, 'name' => 'Maya L', 'email' => $email,
    ]);
    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andReturn($profile);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('driver')->with($provider)->andReturn($driver);
    Socialite::swap($factory);
}

function oauthFlow(string $purpose, string $provider = 'github', ?User $user = null, array $extra = []): void
{
    session()->put('identity.oauth', ['purpose' => $purpose, 'provider' => $provider, 'user_id' => $user?->id, 'next' => $extra['next'] ?? null, 'action' => $extra['action'] ?? null]);
}
