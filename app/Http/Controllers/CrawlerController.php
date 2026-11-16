<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\PublicPages;
use Illuminate\Http\Response;

/**
 * `/robots.txt` and `/sitemap.xml` (A00 SEO audit). Routes, not files in
 * public/, so both carry this instance's APP_URL: a self-hosted instance
 * points crawlers at its own sitemap, never at app.webhooktrap.dev.
 * Outside the `web` group: no session, no cookie.
 */
final class CrawlerController
{
    /** Private surfaces. `/r/example` is the only public `/r/` page (S-14). */
    private const array DISALLOW = [
        '/s/', '/r/', '/p/', '/c/', '/projects', '/account', '/agent', '/runner', '/pairing-codes',
        '/health', '/up', '/horizon', '/broadcasting', '/settings', '/dashboard',
        '/sign-in', '/register', '/forgot-password', '/reset-password', '/two-factor', '/confirm-auth',
        '/verify-email', '/email-change', '/set-password', '/auth/', '/expired', '/webauthn', '/operator/',
    ];

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /', 'Allow: /r/example'];
        foreach (self::DISALLOW as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.PublicPages::url('sitemap.xml');

        return new Response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (PublicPages::SITEMAP_PATHS as $path) {
            $xml .= '  <url><loc>'.htmlspecialchars(PublicPages::url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
