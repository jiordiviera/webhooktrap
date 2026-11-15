<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Reads the real config, so the application is booted (no database is used).
pest()->extend(TestCase::class);

/*
 * Every queue the app dispatches to has a Horizon supervisor in every
 * environment, or its jobs wait forever in production (ops: compose-profile
 * §3.4). Source scan of app/ and routes/ for `onQueue('…')`, `$queue = '…'`,
 * `viaQueue()` returning a literal and `queue: '…'`; `default` is always
 * used (mail and jobs without a queue name). A queue name that is not a
 * string literal fails the scan, so it cannot slip past.
 */

/** @return array<string, list<string>> queue name => files */
function dispatchedQueues(): array
{
    $root = dirname(__DIR__, 2);
    $patterns = [
        '/->onQueue\(\s*([^)]*?)\s*\)/',
        '/\b(?:public|protected|private)\s+(?:\?string\s+|string\s+)?\$queue\s*=\s*([^;]+);/',
        '/function\s+viaQueue\s*\([^)]*\)[^{]*\{\s*return\s+([^;]+);/',
        '/\bqueue:\s*([^,)]+)/',
    ];
    $queues = ['default' => ['(implicit)']];

    foreach (Finder::create()->files()->name('*.php')->in([$root.'/app', $root.'/routes']) as $file) {
        // Code only: comments may say "queue: …" in prose.
        $source = '';
        foreach (PhpToken::tokenize($file->getContents()) as $token) {
            if (! $token->is([T_COMMENT, T_DOC_COMMENT])) {
                $source .= $token->text;
            }
        }
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $source, $matches) > 0) {
                foreach ($matches[1] as $expression) {
                    $name = preg_match("/^'([A-Za-z0-9_.-]+)'$/", trim($expression), $m) === 1
                        ? $m[1]
                        : '<not a literal: '.trim($expression).'>';
                    $queues[$name][] = $file->getRelativePathname();
                }
            }
        }
    }

    return $queues;
}

/** @return array<string, array<string, mixed>> supervisor => options for one environment */
function horizonSupervisors(string $environment): array
{
    $defaults = config('horizon.defaults');
    $environments = config('horizon.environments');
    $overrides = $environments[$environment] ?? $environments['*'] ?? [];
    $supervisors = [];
    foreach ($overrides as $name => $options) {
        $supervisors[$name] = array_merge($defaults[$name] ?? [], $options);
    }

    return $supervisors;
}

it('finds the queues the app dispatches to', function (): void {
    expect(array_keys(dispatchedQueues()))->toContain('default', 'broadcasts', 'maintenance');
});

it('gives every dispatched queue a Horizon supervisor in every environment', function (string $environment): void {
    $served = [];
    foreach (horizonSupervisors($environment) as $options) {
        expect($options['connection'] ?? null)->toBe('redis');
        expect($options['maxProcesses'] ?? 0)->toBeGreaterThanOrEqual(1);
        foreach ((array) $options['queue'] as $queue) {
            $served[$queue] = true;
        }
    }

    $missing = array_diff_key(dispatchedQueues(), $served);
    expect($missing)->toBe([], "queues without a supervisor in `{$environment}`");
})->with(['production', 'local', 'ci', 'staging']);

it('keeps every worker timeout below the queue retry_after', function (string $environment): void {
    $retryAfter = (int) config('queue.connections.redis.retry_after');
    foreach (horizonSupervisors($environment) as $name => $options) {
        expect($options['timeout'])->toBeLessThan($retryAfter, "{$name} in {$environment}");
    }
})->with(['production', 'local', 'ci']);

it('keeps the broadcasts supervisor fast and separate', function (): void {
    foreach (['production', 'local', 'ci'] as $environment) {
        $supervisors = horizonSupervisors($environment);
        $broadcasts = collect($supervisors)->filter(fn (array $o): bool => in_array('broadcasts', (array) $o['queue'], true));
        expect($broadcasts)->toHaveCount(1);
        $options = $broadcasts->first();
        expect((array) $options['queue'])->toBe(['broadcasts'])
            ->and($options['timeout'])->toBeLessThanOrEqual(15)
            ->and($options['tries'])->toBeLessThanOrEqual(3);
    }
});
