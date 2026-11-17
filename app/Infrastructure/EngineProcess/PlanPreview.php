<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

/**
 * The `plan` op's answer after validation (`ReplyDecoder`): codes and
 * numbers only, refs among the request's.
 */
final readonly class PlanPreview
{
    /**
     * @param  array{id: string, version: string}  $pack
     * @param  array{ms: int, source: string}  $budget
     * @param  list<array{name: string, na: string|null, deliveries: int}>  $scenarios
     * @param  list<array{ref: string, eventType: string, primary: bool}>  $events
     */
    public function __construct(
        public int $deliveryCount,
        public string $planHash,
        public bool $needsSecret,
        public array $pack,
        public array $budget,
        public array $scenarios,
        public array $events,
    ) {}

    /** @return array{deliveryCount: int, planHash: string, needsSecret: bool, pack: array{id: string, version: string}, budget: array{ms: int, source: string}, scenarios: list<array{name: string, na: string|null, deliveries: int}>, events: list<array{ref: string, eventType: string, primary: bool}>} */
    public function toArray(): array
    {
        return [
            'deliveryCount' => $this->deliveryCount,
            'planHash' => $this->planHash,
            'needsSecret' => $this->needsSecret,
            'pack' => $this->pack,
            'budget' => $this->budget,
            'scenarios' => $this->scenarios,
            'events' => $this->events,
        ];
    }

    /** Rebuilds a preview from `toArray()` (cache); null when the shape is off. */
    public static function fromArray(mixed $a): ?self
    {
        if (! is_array($a) || ! is_int($a['deliveryCount'] ?? null) || ! is_string($a['planHash'] ?? null) || ! is_bool($a['needsSecret'] ?? null)
            || ! is_array($a['pack'] ?? null) || ! is_array($a['budget'] ?? null) || ! is_array($a['scenarios'] ?? null) || ! is_array($a['events'] ?? null)) {
            return null;
        }

        /** @var array{deliveryCount: int, planHash: string, needsSecret: bool, pack: array{id: string, version: string}, budget: array{ms: int, source: string}, scenarios: list<array{name: string, na: string|null, deliveries: int}>, events: list<array{ref: string, eventType: string, primary: bool}>} $a */
        return new self($a['deliveryCount'], $a['planHash'], $a['needsSecret'], $a['pack'], $a['budget'], $a['scenarios'], $a['events']);
    }
}
