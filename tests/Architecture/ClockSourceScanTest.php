<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Rule 4 (D-10), the part Pest arch() cannot see: static calls such as
 * Carbon::now() or CarbonImmutable::today() name a class that the rest of
 * the code may legitimately use as a type. This test scans the source.
 */
it('calls ::now(), ::today() and friends only inside the Clock adapter', function (): void {
    $offenders = [];

    $files = Finder::create()->files()->name('*.php')->in(dirname(__DIR__, 2).'/app')
        ->notPath('Infrastructure/Clock');

    foreach ($files as $file) {
        $tokens = PhpToken::tokenize($file->getContents());
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if (! $tokens[$i]->is(T_DOUBLE_COLON)) {
                continue;
            }
            $next = $tokens[$i + 1];
            if ($next->is(T_STRING) && in_array(strtolower($next->text), ['now', 'today', 'yesterday', 'tomorrow', 'settestnow'], true)) {
                $offenders[] = $file->getRelativePathname().':'.$next->line;
            }
        }
    }

    expect($offenders)->toBe([]);
});
