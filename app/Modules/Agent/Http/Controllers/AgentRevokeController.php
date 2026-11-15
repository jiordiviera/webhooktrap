<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Modules\Agent\Application\AgentLifecycle;
use App\Modules\Identity\Application\GuestSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `DELETE /agents/{agentId}` (contracts §3.2; S-1 "Not your machine?
 * Revoke"; A09 AC-7): the workspace owner, user or guest, revokes one of its
 * live agents. CSRF-checked (web group). No recent authentication: revoking
 * only removes access, and the threat model asks for it on no agent action.
 * Unknown, revoked or foreign agents are the same 404. JSON clients get
 * `204`; Inertia visits are redirected back (303) with flash
 * `{kind: 'success', code: 'agent_revoked', agentId}`.
 */
final readonly class AgentRevokeController
{
    public function __construct(private GuestSessions $guests, private AgentLifecycle $lifecycle) {}

    public function __invoke(Request $request, string $agentId): JsonResponse|RedirectResponse
    {
        $principal = $this->guests->principal($request);
        if ($principal === null || ! $this->lifecycle->revokeOwned($principal, $agentId)) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return new JsonResponse(['error' => 'not_found'], 404);
            }
            abort(404);
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(null, 204);
        }

        return back(303)->with('flash', ['kind' => 'success', 'code' => 'agent_revoked', 'message' => '', 'agentId' => $agentId]);
    }
}
