<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Modules\Agent\Application\Pairing;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Identity\Application\GuestSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /pairing-codes` (contracts §3.2; S-1): guest or user, CSRF-checked
 * (web group). Optional body `{replace: "agt_…"}` (AC-3): the owner chose
 * to replace one of its confirmed agents. Answers `{code, expiresAt,
 * command, replaces}`; one live code per principal, a new one replaces the
 * old; an unknown, revoked, unconfirmed or foreign agent is `422 unknown_agent`.
 */
final readonly class PairingCodeController
{
    public function __construct(private GuestSessions $guests, private Pairing $pairing) {}

    public function store(Request $request): JsonResponse
    {
        $principal = $this->guests->principal($request);
        if ($principal === null) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }
        $replace = $request->input('replace');
        if ($replace !== null && (! is_string($replace) || preg_match('/^agt_[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $replace) !== 1)) {
            return new JsonResponse(['error' => 'unknown_agent'], 422);
        }
        try {
            return new JsonResponse($this->pairing->issue($principal, $replace), 201, ['Cache-Control' => 'no-store']);
        } catch (AgentRefusal $r) {
            return new JsonResponse(['error' => $r->error], $r->status, $r->headers);
        }
    }
}
