<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The pages search engines may index (A00 SEO audit). An allow-list: every
 * other page, and every page added later, is `noindex,nofollow` until it is
 * named here. The same list feeds the sitemap and robots.txt.
 */
final class PublicPages
{
    /** Inertia components that are public and the same for every visitor. */
    private const array INDEXABLE_COMPONENTS = ['welcome', 'Legal/Privacy', 'Legal/Terms'];

    /** Paths listed in the sitemap, in order. */
    public const array SITEMAP_PATHS = ['/', '/privacy', '/terms', '/r/example'];

    /**
     * The example run (S-14) shares `Run/Show` with the owner run pages, so it
     * is told apart by its `access` prop, never by the component alone.
     *
     * @param  array{component: string, props?: array<string, mixed>}  $page
     */
    public static function indexable(array $page): bool
    {
        if (in_array($page['component'], self::INDEXABLE_COMPONENTS, true)) {
            return true;
        }

        return $page['component'] === 'Run/Show' && ($page['props']['access'] ?? null) === 'example';
    }

    /**
     * The canonical URL: APP_URL and the path, never the query string. The
     * example run's focus views (`/r/example/D3`) point at `/r/example`.
     *
     * @param  array{component: string, props?: array<string, mixed>}  $page
     */
    public static function canonical(array $page, string $path): string
    {
        $path = trim($path, '/');
        if ($page['component'] === 'Run/Show' && ($page['props']['access'] ?? null) === 'example') {
            $path = 'r/example';
        }

        return self::url($path);
    }

    public static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * The hashed build URLs of the fonts the landing's first paint uses (the
     * H1 in Archivo, the terminal in Martian Mono), latin subsets only. Empty
     * under the Vite dev server or without a build.
     *
     * @return list<string>
     */
    public static function landingFontUrls(): array
    {
        $manifest = public_path('build/manifest.json');
        if (is_file(public_path('hot')) || ! is_file($manifest)) {
            return [];
        }
        $entries = json_decode((string) file_get_contents($manifest), true);
        if (! is_array($entries)) {
            return [];
        }

        $urls = [];
        foreach (['/archivo-latin-wdth-normal.woff2', '/martian-mono-latin-wdth-normal.woff2'] as $suffix) {
            foreach ($entries as $key => $entry) {
                if (is_string($key) && str_ends_with($key, $suffix) && is_array($entry) && is_string($entry['file'] ?? null)) {
                    $urls[] = asset('build/'.$entry['file']);

                    break;
                }
            }
        }

        return $urls;
    }
}
