<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * ADR-005 amendment 1 §7: under FrankenPHP worker mode no request-scoped
 * state may live in static properties. Modules and adapters declare none
 * (constants are fine). Source scan, because Pest arch() has no such rule.
 */
it('declares no static properties in modules or infrastructure adapters', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $offenders = [];

    foreach (Finder::create()->files()->name('*.php')->in([$root.'/Modules', $root.'/Infrastructure']) as $file) {
        $source = $file->getContents();
        $pattern = '/\b(?:(?:public|protected|private|var)\s+(?:readonly\s+)?static|static\s+(?:public|protected|private))\s+(?!function\b|fn\b)/';
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [, $offset]) {
                $offenders[] = $file->getRelativePathname().':'.(substr_count(substr($source, 0, $offset), "\n") + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
