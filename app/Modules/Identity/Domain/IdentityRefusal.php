<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A refusal with a contract error code (contracts §3.4), e.g.
 * `last_sign_in_method`, `recovery_lock`, `confirmation_stale`.
 * JSON callers get `422 {"error": code}`; Inertia forms get `errors.form`.
 */
final class IdentityRefusal extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly string $field = 'form')
    {
        parent::__construct($errorCode);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['error' => $this->errorCode], 422);
        }

        return back()->withErrors([$this->field => $this->errorCode]);
    }
}
