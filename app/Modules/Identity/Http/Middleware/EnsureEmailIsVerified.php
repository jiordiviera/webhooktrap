<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Domain\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Workspace features need a verified email (domain §2.1). */
final class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->isVerified()) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['error' => 'email_unverified'], 403)
                : redirect('/verify-email');
        }

        return $next($request);
    }
}
