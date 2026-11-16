<?php

declare(strict_types=1);

namespace App\Http\Ssr;

use App\Http\PublicPages;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Inertia\Ssr\ConfiguresSsrRequests;
use Inertia\Ssr\DisablesSsr;
use Inertia\Ssr\ExcludesSsrPaths;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;
use Inertia\Ssr\HttpGateway;
use Inertia\Ssr\Response;

/**
 * SSR for the public pages only (PO decision 2026-11-16). A page goes to the
 * render server when App\Http\PublicPages lists it as indexable and the
 * visitor is anonymous; everything else, including a public page seen by a
 * signed-in user or a guest session, renders in the browser. So no account,
 * workspace or session data ever reaches the SSR process.
 *
 * Fails closed: a page without the shared `principal` prop is not rendered.
 * Inertia's HttpGateway does the call; a failure or a timeout returns null
 * and the page falls back to client rendering (config/inertia.php).
 */
final readonly class PublicPagesSsrGateway implements ConfiguresSsrRequests, DisablesSsr, ExcludesSsrPaths, Gateway, HasHealthCheck
{
    public function __construct(private HttpGateway $gateway)
    {
        // HttpGateway only applies `timeout`; a black-holed host would wait
        // for the HTTP client's default connect timeout (10 s) otherwise.
        $gateway->configureRequestUsing(static fn (PendingRequest $request): PendingRequest => $request
            ->connectTimeout(max(0.05, (float) config('inertia.ssr.connect_timeout')))
            ->timeout(max(0.05, (float) config('inertia.ssr.timeout'))));
    }

    /** @param array<string, mixed> $page */
    public function dispatch(array $page): ?Response
    {
        return self::renders($page) ? $this->gateway->dispatch($page) : null;
    }

    /**
     * Whether a page may be server-rendered: public and anonymous only.
     *
     * @param  array<string, mixed>  $page
     */
    public static function renders(array $page): bool
    {
        $component = $page['component'] ?? null;
        $props = $page['props'] ?? null;
        if (! is_string($component) || ! is_array($props)) {
            return false;
        }

        $principal = $props['principal'] ?? null;
        if (! is_array($principal) || ($principal['kind'] ?? null) !== 'anonymous') {
            return false;
        }

        return PublicPages::indexable(['component' => $component, 'props' => $props]);
    }

    public function disable(Closure|bool $condition): void
    {
        $this->gateway->disable($condition);
    }

    /** @param array<int, string>|string $paths */
    public function except(array|string $paths): void
    {
        $this->gateway->except($paths);
    }

    public function configureRequestUsing(?Closure $callback = null): void
    {
        $this->gateway->configureRequestUsing($callback);
    }

    public function isHealthy(): bool
    {
        return $this->gateway->isHealthy();
    }
}
