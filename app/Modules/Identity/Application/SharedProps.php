<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\User;
use Illuminate\Http\Request;

/**
 * Shared props owned by Identity (contracts §3.1): `principal`, the guest
 * `meter`, `instance` and `flash`. Nothing else of a session leaks here.
 */
final readonly class SharedProps
{
    public function __construct(private GuestSessions $guests, private AccountRecovery $recovery) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $props = [
            'instance' => [
                'kind' => config('identity.instance') === 'selfhost' ? 'selfhost' : 'hosted',
                'name' => (string) config('app.name'),
                'guestMode' => (bool) config('identity.guest_mode'),
                'serverRuns' => false,
            ],
        ];

        $user = $request->user();
        if ($user instanceof User) {
            $open = $this->recovery->open($user);
            $props['principal'] = array_filter([
                'kind' => 'user',
                'id' => $user->publicId(),
                'name' => (string) $user->name,
                'email' => $user->email,
                'emailVerified' => $user->isVerified(),
                'isInstanceAdmin' => $user->is_instance_admin,
                'twoFactor' => $user->two_factor_enabled_at !== null,
                'recoveryPending' => SecurityViews::recoveryPending($open),
            ], fn ($v) => $v !== null);
        } elseif ($guest = $this->guests->current()) {
            $props['principal'] = ['kind' => 'guest', 'expiresAt' => SecurityViews::iso($guest->expires_at), 'ended' => false];
            $props['meter'] = $this->guests->meter($guest);
        } else {
            $props['principal'] = ['kind' => 'anonymous'];
        }

        $flash = $request->hasSession() ? $request->session()->get('flash') : null;
        if (is_array($flash)) {
            $props['flash'] = $flash;
        }

        return $props;
    }
}
