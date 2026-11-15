<?php

declare(strict_types=1);

namespace App\Modules\Proving\Http\Controllers;

use App\Modules\Proving\Domain\ExampleRun;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S-14 · `GET /r/example` (+ `/D{n}`, `/F{n}`): the example run for
 * everyone, signed in or not (contracts §3.2, `access: 'example'`). Static,
 * not counted anywhere, never modified.
 */
final readonly class ExampleRunController
{
    public function __construct(private ExampleRun $example) {}

    public function show(?string $focus = null): Response
    {
        $props = $this->example->props();

        return Inertia::render('Run/Show', [
            ...$props,
            'focus' => $this->example->focus($focus, $props),
            'access' => 'example',
        ]);
    }
}
