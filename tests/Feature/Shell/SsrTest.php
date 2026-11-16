<?php

declare(strict_types=1);

use App\Http\Ssr\PublicPagesSsrGateway;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Ssr\Gateway;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * SSR for the public pages only (PO decision 2026-11-16): the landing, the
 * legal pages and the example run, for anonymous visitors. Every other page,
 * and a public page seen by a signed-in user, never reaches the render
 * server. A dead or slow render server falls back to client rendering.
 */

const SSR_TEST_URL = 'http://ssr.test-render:13714';
const SSR_MARKER = 'data-server-rendered="true"';
const CSR_ROOT = '<div id="app"></div>';

beforeEach(function (): void {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.url' => SSR_TEST_URL,
        'inertia.ssr.ensure_bundle_exists' => false,
        'inertia.ssr.timeout' => 0.5,
        'inertia.ssr.connect_timeout' => 0.25,
    ]);
    // Keep the dev server's hot file out of the way (Vite::isRunningHot()).
    app('Illuminate\Foundation\Vite')->useHotFile(storage_path('framework/ssr-test-no-hot'));
});

function fakeRenderServer(): void
{
    Http::fake([
        SSR_TEST_URL.'/render' => fn (Request $request) => Http::response([
            'head' => ['<title inertia>SSR head</title>'],
            'body' => '<script data-page="app" type="application/json">{}</script><div '.SSR_MARKER.' id="app"><h1>rendered '.$request['component'].'</h1></div>',
        ]),
    ]);
}

it('binds the public-pages gateway', function (): void {
    expect(app(Gateway::class))->toBeInstanceOf(PublicPagesSsrGateway::class);
});

it('server-renders the public pages for anonymous visitors', function (string $path, string $component): void {
    fakeRenderServer();

    $this->get($path)
        ->assertOk()
        ->assertSee(SSR_MARKER, false)
        ->assertSee('<h1>rendered '.$component.'</h1>', false)
        ->assertSee('<title inertia>SSR head</title>', false)
        ->assertDontSee(CSR_ROOT, false);

    Http::assertSent(fn (Request $request): bool => $request->url() === SSR_TEST_URL.'/render'
        && $request['component'] === $component
        && $request['props']['principal'] === ['kind' => 'anonymous']);
})->with([
    ['/', 'welcome'],
    ['/privacy', 'Legal/Privacy'],
    ['/terms', 'Legal/Terms'],
    ['/r/example', 'Run/Show'],
]);

it('never sends a private page to the render server', function (string $path): void {
    fakeRenderServer();

    $this->get($path)->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);

    Http::assertNothingSent();
})->with(['/sign-in', '/register', '/forgot-password']);

it('never sends a signed-in page to the render server', function (): void {
    fakeRenderServer();
    signIn(User::factory()->create());

    $this->followingRedirects()->get('/projects')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
    $this->followingRedirects()->get('/account/preferences')->assertOk()->assertDontSee(SSR_MARKER, false);
    // A public page seen by a signed-in user carries their principal: no SSR either.
    $this->get('/privacy')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);

    Http::assertNothingSent();
});

it('decides from the component, the example access and the principal', function (array $page, bool $renders): void {
    expect(PublicPagesSsrGateway::renders($page))->toBe($renders);
})->with([
    'landing' => [['component' => 'welcome', 'props' => ['principal' => ['kind' => 'anonymous']]], true],
    'example run' => [['component' => 'Run/Show', 'props' => ['access' => 'example', 'principal' => ['kind' => 'anonymous']]], true],
    'owner run' => [['component' => 'Run/Show', 'props' => ['access' => 'owner', 'principal' => ['kind' => 'anonymous']]], false],
    'user' => [['component' => 'welcome', 'props' => ['principal' => ['kind' => 'user', 'email' => 'a@b.c']]], false],
    'guest' => [['component' => 'welcome', 'props' => ['principal' => ['kind' => 'guest']]], false],
    'no principal' => [['component' => 'welcome', 'props' => []], false],
    'private page' => [['component' => 'Projects/Index', 'props' => ['principal' => ['kind' => 'anonymous']]], false],
]);

it('falls back to client rendering when the render server is down', function (): void {
    Http::fake([SSR_TEST_URL.'/*' => fn () => throw new ConnectionException('Connection refused')]);

    $this->get('/')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
});

it('falls back to client rendering when the render server fails', function (): void {
    Http::fake([SSR_TEST_URL.'/*' => Http::response(['error' => 'window is not defined', 'type' => 'browser-api'], 500)]);

    $this->get('/')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
});

it('gives up on a render server that accepts but never answers', function (): void {
    // A listening socket that is never accepted: the TCP handshake completes
    // (backlog) and the request then waits for an answer that never comes.
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    expect($server)->not->toBeFalse();
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
    config(['inertia.ssr.url' => "http://127.0.0.1:{$port}"]);

    $started = hrtime(true);
    $this->get('/')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
    $seconds = (hrtime(true) - $started) / 1e9;

    expect($seconds)->toBeLessThan(2.0);
    fclose($server);
});

it('falls back fast on a closed port', function (): void {
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
    fclose($server);
    config(['inertia.ssr.url' => "http://127.0.0.1:{$port}"]);

    $this->get('/terms')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
});

it('never throws on a render error outside tests', function (): void {
    $config = require base_path('config/inertia.php');

    expect($config['ssr']['throw_on_error'])->toBeFalse()
        ->and($config['ssr']['timeout'])->toBeLessThanOrEqual(2.0)
        ->and($config['ssr']['connect_timeout'])->toBeLessThanOrEqual(1.0);
});

it('renders the landing H1 with the real SSR bundle', function (): void {
    $bundle = base_path('bootstrap/ssr/ssr.js');
    $node = (new ExecutableFinder)->find('node');
    if (! is_file($bundle) || $node === null) {
        $this->markTestSkipped('No SSR bundle (pnpm run build) or no node binary.');
    }
    if (@fsockopen('127.0.0.1', 13714, $errno, $error, 0.2)) {
        $this->markTestSkipped('Port 13714 is busy (another SSR server).');
    }

    $process = new Process([$node, $bundle], base_path());
    $process->start();
    try {
        $deadline = microtime(true) + 10;
        while (! @fsockopen('127.0.0.1', 13714, $errno, $error, 0.2) && microtime(true) < $deadline) {
            usleep(100_000);
        }
        config(['inertia.ssr.url' => 'http://127.0.0.1:13714', 'inertia.ssr.timeout' => 5.0]);

        $this->get('/')->assertOk()
            ->assertSee(SSR_MARKER, false)
            ->assertSee('id="lp-h1"', false)
            ->assertSee('>Your webhook handler works. Once.<span', false);
        $this->get('/terms')->assertOk()->assertSee(SSR_MARKER, false)->assertSee('</h1>', false);
        $this->get('/sign-in')->assertOk()->assertSee(CSR_ROOT, false)->assertDontSee(SSR_MARKER, false);
    } finally {
        $process->stop(1);
    }
});
