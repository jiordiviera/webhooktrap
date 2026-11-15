<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\SecurityViews;
use App\Modules\Identity\Application\WebAuthnCeremonies;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WebAuthn JSON endpoints (contracts §3.4). Session, CSRF and
 * `Sec-Fetch-Site: same-origin` are required (route middleware); refusals
 * render as `422 {"error": code}` (IdentityRefusal).
 */
final readonly class WebAuthnController
{
    public function __construct(private WebAuthnCeremonies $ceremonies, private Clock $clock) {}

    /** `POST /webauthn/register/options` `{kind}` */
    public function registerOptions(Request $request): Response
    {
        $kind = (string) $request->validate(['kind' => ['required', 'string', 'in:passkey,security_key']])['kind'];

        return self::json($this->ceremonies->registrationOptions($request, $this->user($request), $kind));
    }

    /** `POST /webauthn/register` `{credential, name}` → SecurityKeyRow (+ recovery codes once) */
    public function register(Request $request, SecurityViews $views): JsonResponse
    {
        $request->validate(['credential' => ['required'], 'name' => ['nullable', 'string', 'max:200']]);

        $name = $request->input('name');
        $result = $this->ceremonies->register($request, $this->user($request), self::credential($request), is_string($name) ? $name : null);
        $row = $views->keyRow($result['key']);
        if ($result['recoveryCodes'] !== null) {
            $row['recoveryCodes'] = $result['recoveryCodes']; // shown once
        }

        return new JsonResponse($row, 201);
    }

    /** `POST /webauthn/assert/options` `{purpose?: 'confirm'}` */
    public function assertOptions(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $confirm = ($request->input('purpose') ?? $request->query('purpose')) === 'confirm';

        $options = $this->ceremonies->assertionOptions($request, $user instanceof User ? $user : null, $confirm);

        return $options instanceof CarbonImmutable ? $this->throttled($options) : self::json($options);
    }

    /** `POST /webauthn/assert` `{credential}` → `{redirect}` */
    public function assert(Request $request): JsonResponse
    {
        $request->validate(['credential' => ['required']]);
        if ($request->user() !== null) {
            return new JsonResponse(['error' => 'not_applicable'], 422);
        }

        $outcome = $this->ceremonies->assert($request, self::credential($request));

        return match ($outcome->status) {
            SignInOutcome::SIGNED_IN => new JsonResponse(['redirect' => SignInController::afterSignIn($outcome->user)->getTargetUrl()]),
            SignInOutcome::THROTTLED => $this->throttled($outcome->retryAt ?? $this->clock->now()),
            SignInOutcome::RESTART => new JsonResponse(['error' => 'challenge_expired', 'redirect' => '/sign-in'], 422),
            default => new JsonResponse(['error' => 'assertion_failed'], 422),
        };
    }

    private function throttled(CarbonImmutable $retryAt): JsonResponse
    {
        return new JsonResponse(['error' => 'throttled'], 429, [
            'Retry-After' => (string) max(1, $retryAt->getTimestamp() - $this->clock->now()->getTimestamp()),
        ]);
    }

    private static function json(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    private static function credential(Request $request): string
    {
        $credential = $request->input('credential');

        return is_string($credential) ? $credential : (string) json_encode($credential, JSON_UNESCAPED_SLASHES);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
