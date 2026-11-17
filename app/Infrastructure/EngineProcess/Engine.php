<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

/**
 * The engine sandbox port (ADR-010 §1): network-free `plan` over the
 * `serve-sandbox` socket. Implementations never throw for engine faults:
 * every failure is a `PlanResult` error code, and the preview they return
 * has been validated field by field (output is untrusted, S-11, TM-74).
 */
interface Engine
{
    public function plan(PlanRequest $request): PlanResult;
}
