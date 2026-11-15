<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http;

use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Domain\JsonSchema;
use App\Modules\Proving\Contracts\RunRefusal;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Request and answer plumbing of `/agent/v1` (contracts §5): every body is
 * decoded once, checked against its schema in `contracts/agent/v1/`
 * (closed objects refuse unknown fields), and every answer is JSON with
 * `"proto": 1`. Bodies are never logged.
 */
final readonly class AgentApi
{
    public const string AGENT = 'agent.view';

    public function __construct(private JsonSchema $schemas) {}

    /**
     * The decoded body as arrays, after schema validation.
     *
     * @return array<string, mixed>
     */
    public function body(Request $request, string $schema): array
    {
        try {
            $decoded = json_decode($request->getContent(), false, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AgentRefusal(400, 'invalid_json');
        }
        $error = $this->schemas->validate($schema, $decoded);
        if ($error !== null) {
            throw new AgentRefusal(422, 'schema', ['detail' => $error]);
        }
        /** @var array<string, mixed> $array */
        $array = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);

        return $array;
    }

    public static function agent(Request $request): AgentView
    {
        $agent = $request->attributes->get(self::AGENT);
        if (! $agent instanceof AgentView) {
            throw new AgentRefusal(401, 'unauthenticated');
        }

        return $agent;
    }

    /** The authenticated agent, refused with 403 until its local confirmation (RE-1, TM-42). */
    public static function confirmedAgent(Request $request): AgentView
    {
        $agent = self::agent($request);
        if ($agent->needsReconfirm) {
            throw AgentRefusal::needsReconfirm();
        }

        return $agent;
    }

    /** @param  array<string, mixed>  $data */
    public static function ok(array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['proto' => 1, ...$data], $status, ['Cache-Control' => 'no-store']);
    }

    /** Runs the action and turns refusals into protocol answers. */
    public static function answer(Closure $action): JsonResponse|Response
    {
        try {
            return $action();
        } catch (AgentRefusal $r) {
            return new JsonResponse(['proto' => 1, 'error' => $r->error, ...$r->extra], $r->status, ['Cache-Control' => 'no-store', ...$r->headers]);
        } catch (RunRefusal $r) {
            return new JsonResponse(['proto' => 1, 'error' => $r->error, ...$r->extra], $r->status, ['Cache-Control' => 'no-store']);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            // A server fault (e.g. a schema the code expects but the database
            // lacks) is reported and answered in the protocol's shape, so the
            // agent backs off and retries instead of reading an HTML or
            // framework body. Nothing of the exception reaches the agent.
            report($e);

            return new JsonResponse(['proto' => 1, 'error' => 'internal_error'], 500, [
                'Cache-Control' => 'no-store', 'Retry-After' => (string) config('agent.longpoll.retry_after_seconds'),
            ]);
        }
    }
}
