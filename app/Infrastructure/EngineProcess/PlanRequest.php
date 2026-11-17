<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

/**
 * Input of the sandbox `plan` op (agent/internal/sandbox/handle.go
 * `PlanInput`): the selected captures with their headers in received order
 * and raw bodies, the scenarios and the pinned pack.
 */
final readonly class PlanRequest
{
    /**
     * @param  list<array{ref: string, order: int, headers: list<array{0: string, 1: string}>, body: string}>  $captures
     * @param  list<string>  $scenarios
     */
    public function __construct(
        public array $captures,
        public array $scenarios,
        public string $packId,
        public string $packVersion,
        public bool $hasSecret = false,
        public int $budgetMs = 0,
    ) {}

    /** @return list<string> */
    public function refs(): array
    {
        return array_map(fn (array $c): string => $c['ref'], $this->captures);
    }

    /** @return array<string, mixed> the wire `input` object */
    public function input(): array
    {
        $input = [
            'captures' => array_map(fn (array $c): array => [
                'ref' => $c['ref'],
                'order' => $c['order'],
                'headers' => array_map(fn (array $h): array => [$h[0], $h[1]], $c['headers']),
                'body_base64' => base64_encode($c['body']),
            ], $this->captures),
            'scenarios' => $this->scenarios,
            'pack' => ['id' => $this->packId, 'version' => $this->packVersion],
        ];
        if ($this->hasSecret) {
            $input['has_secret'] = true;
        }
        if ($this->budgetMs > 0) {
            $input['budget_ms'] = $this->budgetMs;
        }

        return $input;
    }
}
