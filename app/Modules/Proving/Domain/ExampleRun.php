<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

use RuntimeException;

/**
 * S-14 example run (decision 63): Run #3 from the realistic data, a static
 * synthetic run shaped like the `Run/Show` props of contracts §3.2/§3.3
 * (`RunHeader`, `RunDeliveryRow`, `FindingView`) plus documented example
 * extras (scenario cells, inspector fields). Counts only, synthetic
 * readings (A09 §2.4). Built by project/design/gen_example_run_fixture.py;
 * no database: the real Run aggregate comes with the engine and agent slices.
 */
final class ExampleRun
{
    /**
     * @return array{
     *     run: array<string, mixed>,
     *     scenarios: list<array<string, mixed>>,
     *     timeline: array{lastSeq: int, status: string, deliveries: list<array<string, mixed>>},
     *     findings: list<array<string, mixed>>,
     *     example: array{label: string, helper: string}
     * }
     */
    public function props(): array
    {
        $json = file_get_contents(__DIR__.'/example-run.json');

        if ($json === false) {
            throw new RuntimeException('Example run fixture missing.');
        }

        /** @var array{run: array<string, mixed>, scenarios: list<array<string, mixed>>, timeline: array{lastSeq: int, status: string, deliveries: list<array<string, mixed>>}, findings: list<array<string, mixed>>, example: array{label: string, helper: string}} $props */
        $props = json_decode($json, true, 64, JSON_THROW_ON_ERROR);

        return $props;
    }

    /**
     * `D5` / `F2` deep links (S-11). An unknown number still loads the run,
     * with `missing` so the page can say so inline.
     *
     * @param  array{timeline: array{deliveries: list<array<string, mixed>>}, findings: list<array<string, mixed>>}  $props
     * @return array{kind: 'delivery'|'finding', n: int, missing: bool}|null
     */
    public function focus(?string $ref, array $props): ?array
    {
        if ($ref === null || preg_match('/^([DF])(\d{1,3})$/', $ref, $m) !== 1) {
            return null;
        }
        $n = (int) $m[2];
        $kind = $m[1] === 'D' ? 'delivery' : 'finding';
        $items = $kind === 'delivery' ? $props['timeline']['deliveries'] : $props['findings'];
        $key = $kind === 'delivery' ? 'd' : 'n';
        $found = array_filter($items, fn (array $item): bool => ($item[$key] ?? null) === $n);

        return ['kind' => $kind, 'n' => $n, 'missing' => $found === []];
    }
}
