<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

/**
 * The scenarios in plan order (PLAN-1) and their display names (A-12),
 * as the engine names them (`agent/plan`).
 */
final class Scenarios
{
    public const array ALL = ['once', 'twice', 'shuffled', 'late', 'retry', 'tampered', 'unsigned', 'burst', 'slow_budget'];

    private const array LABELS = [
        'once' => 'Once', 'twice' => 'Twice', 'shuffled' => 'Shuffled', 'late' => 'Late',
        'retry' => 'Retry after error', 'tampered' => 'Tampered', 'unsigned' => 'Unsigned',
        'burst' => 'Burst', 'slow_budget' => 'Slow budget',
    ];

    public static function label(string $scenario): string
    {
        return self::LABELS[$scenario] ?? $scenario;
    }

    /**
     * Selected scenarios in plan order; unknown names dropped.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public static function ordered(array $names): array
    {
        return array_values(array_filter(self::ALL, fn (string $s): bool => in_array($s, $names, true)));
    }
}
