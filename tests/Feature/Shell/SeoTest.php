<?php

declare(strict_types=1);

use App\Http\PublicPages;
use App\Modules\Identity\Domain\User;

/*
 * Crawler surface (A00 SEO audit): canonical URLs, an allow-list of
 * indexable pages (everything else noindex), robots.txt and sitemap.xml
 * written with APP_URL, and structured data on the landing only.
 */

function seoUrl(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
}

it('marks the public pages indexable', function (string $path): void {
    $this->get($path)
        ->assertOk()
        ->assertSee('<meta name="robots" content="index,follow">', false)
        ->assertDontSee('noindex', false);
})->with(['/', '/privacy', '/terms', '/r/example']);

it('marks every other page noindex', function (string $path): void {
    $this->get($path)
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
        ->assertDontSee('content="index,follow"', false);
})->with(['/sign-in', '/register', '/forgot-password']);

it('marks the signed-in pages noindex', function (): void {
    signIn(User::factory()->create());

    $this->followingRedirects()->get('/projects')->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    $this->followingRedirects()->get('/account/preferences')->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});

it('emits a canonical URL from APP_URL without the query string', function (): void {
    $this->get('/?utm_source=x')->assertOk()->assertSee('<link rel="canonical" href="'.seoUrl('/').'">', false);
    $this->get('/privacy?ref=y')->assertOk()->assertSee('<link rel="canonical" href="'.seoUrl('privacy').'">', false);
});

it('points the example run focus views at the example run', function (): void {
    $this->get('/r/example/D1')->assertOk()->assertSee('<link rel="canonical" href="'.seoUrl('r/example').'">', false);
});

it('serves robots.txt with the private prefixes and this instance sitemap', function (): void {
    $response = $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    $body = (string) $response->getContent();

    expect($body)->toContain("User-agent: *\nAllow: /\n")
        ->toContain('Allow: /r/example')
        ->toContain('Sitemap: '.seoUrl('sitemap.xml'));
    foreach (['/s/', '/r/', '/p/', '/c/', '/projects', '/account', '/agent', '/runner', '/health', '/sign-in', '/register'] as $prefix) {
        expect($body)->toContain("Disallow: {$prefix}\n");
    }
    expect($response->headers->getCookies())->toBe([]);
});

it('ships no static robots.txt that would shadow the route', function (): void {
    expect(public_path('robots.txt'))->not->toBeFile();
});

it('serves a sitemap of the public pages', function (): void {
    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('application/xml')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600')
        ->and($response->headers->getCookies())->toBe([]);

    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml)->not->toBeFalse();
    $locs = [];
    foreach ($xml->url as $url) {
        $locs[] = (string) $url->loc;
    }
    expect($locs)->toBe(array_map(seoUrl(...), PublicPages::SITEMAP_PATHS));
});

it('puts the SoftwareApplication JSON-LD on the landing', function (): void {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(preg_match('~<script type="application/ld\+json">(.+?)</script>~s', $html, $m))->toBe(1);
    $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    $types = array_column($data['@graph'], '@type');
    $app = $data['@graph'][array_search('SoftwareApplication', $types, true)];

    expect($types)->toContain('SoftwareApplication', 'Organization', 'WebSite')
        ->and($app['name'])->toBe('Webhook Trap')
        ->and($app['applicationCategory'])->toBe('DeveloperApplication')
        ->and($app['operatingSystem'])->toBe('Linux, macOS, Windows')
        ->and($app['offers']['price'])->toBe('0')
        ->and($app['license'])->toBe('https://www.gnu.org/licenses/agpl-3.0.html')
        ->and($app['codeRepository'])->toBe('https://github.com/jiordiviera/webhooktrap')
        ->and($app['url'])->toBe(seoUrl('/'));
});

it('keeps JSON-LD off every other page', function (string $path): void {
    $this->get($path)->assertOk()->assertDontSee('application/ld+json', false);
})->with(['/privacy', '/terms', '/sign-in', '/r/example']);

it('links the web manifest, which names existing icons', function (): void {
    $this->get('/sign-in')->assertOk()->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);

    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['theme_color'])->toBe('#141411')
        ->and($manifest['background_color'])->toBe('#141411');
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('sends X-Robots-Tag on the health check', function (): void {
    $this->get('/health')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('preloads the landing fonts from the build manifest on the landing only', function (): void {
    $public = sys_get_temp_dir().'/wt-seo-'.bin2hex(random_bytes(4));
    mkdir($public.'/build', 0o777, true);
    $pnpm = 'node_modules/.pnpm/x/node_modules/@fontsource-variable';
    file_put_contents($public.'/build/manifest.json', json_encode([
        'resources/css/app.css' => ['file' => 'assets/app.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
        'resources/js/app.tsx' => ['file' => 'assets/app.js', 'src' => 'resources/js/app.tsx', 'isEntry' => true],
        'resources/js/pages/welcome.tsx' => ['file' => 'assets/welcome.js', 'src' => 'resources/js/pages/welcome.tsx'],
        "{$pnpm}/archivo/files/archivo-latin-ext-wdth-normal.woff2" => ['file' => 'assets/archivo-latin-ext.woff2'],
        "{$pnpm}/archivo/files/archivo-latin-wdth-normal.woff2" => ['file' => 'assets/archivo-latin.woff2'],
        "{$pnpm}/martian-mono/files/martian-mono-latin-wdth-normal.woff2" => ['file' => 'assets/martian-latin.woff2'],
    ]));
    $this->app->usePublicPath($public);

    try {
        expect(PublicPages::landingFontUrls())->toBe([
            asset('build/assets/archivo-latin.woff2'),
            asset('build/assets/martian-latin.woff2'),
        ]);
        $this->get('/')->assertOk()
            ->assertSee('<link rel="preload" href="'.asset('build/assets/archivo-latin.woff2').'" as="font" type="font/woff2" crossorigin>', false)
            ->assertDontSee('archivo-latin-ext', false);
    } finally {
        array_map(unlink(...), [$public.'/build/manifest.json']);
        rmdir($public.'/build');
        rmdir($public);
    }
});

it('preloads no font off the landing', function (): void {
    $this->get('/sign-in')->assertOk()->assertDontSee('rel="preload" href', false);
});
