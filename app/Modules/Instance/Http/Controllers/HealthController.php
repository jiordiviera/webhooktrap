<?php

declare(strict_types=1);

namespace App\Modules\Instance\Http\Controllers;

use App\Modules\Instance\Application\Health;
use Illuminate\Http\JsonResponse;

/**
 * `GET /health`: the deep check for the external uptime monitor (not for
 * Docker, which uses `/up`). Outside the `web` group: no session, no cookie.
 */
final readonly class HealthController
{
    public function __invoke(Health $health): JsonResponse
    {
        $report = $health->report();

        return new JsonResponse($report, $report['ok'] === true ? 200 : 503, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
