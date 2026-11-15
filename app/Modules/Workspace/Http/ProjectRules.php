<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Validation of the project forms (S-15, S-16); copy from the screen specs where it exists. */
final class ProjectRules
{
    /** @return list<string> */
    public static function name(): array
    {
        return ['required', 'string', 'max:80'];
    }

    /** @return list<string|In> */
    public static function captureDays(): array
    {
        return ['required', 'integer', Rule::in(self::ints('workspace.retention.capture_days'))];
    }

    /** @return list<string|In> */
    public static function runDays(): array
    {
        return ['required', 'integer', Rule::in(self::ints('workspace.retention.run_days'))];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'name.string' => 'Enter a name.',
            'name.max' => 'Use 80 characters or fewer.',
            'captureRetentionDays.*' => 'Choose how long deliveries are kept.',
            'runRetentionDays.*' => 'Choose how long runs are kept.',
        ];
    }

    /** @return list<int> */
    public static function ints(string $key): array
    {
        $values = config($key);

        return is_array($values) ? array_values(array_map(intval(...), $values)) : [];
    }
}
