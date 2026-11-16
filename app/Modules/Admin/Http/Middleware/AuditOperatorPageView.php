<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Modules\Identity\Application\OperatorAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin page view goes to the security log, `audit_log` (decision
 * 74): who (actor), which page (path, no query string) and when. Livewire
 * updates are interactions on an already audited page and are not logged.
 * Runs after Filament's `Authenticate`, so only admitted operators are logged.
 */
final readonly class AuditOperatorPageView
{
    public function __construct(private OperatorAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user !== null && $request->isMethod('GET') && ! $request->hasHeader('X-Livewire') && $response->getStatusCode() < 400) {
            $this->access->pageViewed($request, (string) $user->getAuthIdentifier(), '/'.ltrim($request->path(), '/'));
        }

        return $response;
    }
}
