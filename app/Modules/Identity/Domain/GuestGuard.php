<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * The `guest` guard (ADR-003 amendment 1 §5): the principal comes only from
 * the `__Host-wt_guest` cookie, never from a URL or a header, and only while
 * the session is live (not expired, ended or claimed).
 */
final class GuestGuard implements Guard
{
    private ?GuestSession $resolved = null;

    private bool $looked = false;

    public function __construct(private Request $request, private readonly Clock $clock) {}

    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->resolved = null;
        $this->looked = false;
    }

    public function check(): bool
    {
        return $this->guestSession() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?GuestSession
    {
        return $this->guestSession();
    }

    /** Same as user(), typed for callers. */
    public function guestSession(): ?GuestSession
    {
        if (! $this->looked) {
            $this->looked = true;
            $this->resolved = $this->lookup();
        }

        return $this->resolved;
    }

    public function id(): ?string
    {
        return $this->guestSession()?->id;
    }

    /** @param  array<string, mixed>  $credentials */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->resolved !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->resolved = $user instanceof GuestSession ? $user : null;
        $this->looked = true;

        return $this;
    }

    public function forget(): void
    {
        $this->resolved = null;
        $this->looked = true;
    }

    /** The session the cookie names, live or not (for `/expired`). */
    public function sessionFromCookie(): ?GuestSession
    {
        $token = $this->request->cookies->get((string) config('identity.guest.cookie'));
        if (! is_string($token) || strlen($token) < 40 || strlen($token) > 64) {
            return null;
        }

        return GuestSession::query()->where('token_hash', Bytea::bind(Tokens::hash($token)))->first();
    }

    private function lookup(): ?GuestSession
    {
        $session = $this->sessionFromCookie();

        return $session !== null && $session->isLiveAt($this->clock->now()) ? $session : null;
    }
}
