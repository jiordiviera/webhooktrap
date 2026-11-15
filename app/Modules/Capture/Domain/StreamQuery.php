<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * S-03 query state (IA §5.2: `?sel=&tab=&view=&q=&group=`), plus `before`
 * for older pages. Anything malformed falls back to the default rather than
 * erroring, so a hand-edited URL still opens the stream.
 *
 * `q` filters on derived and request-line facts only (event type, provider,
 * provider event id, object ids, method, path, `#seq`), never on bodies or
 * header values: `q` lives in the URL, and the app never puts payload values
 * into a URL (IA §5.2 rule 5).
 */
final readonly class StreamQuery
{
    public const array TABS = ['verdict', 'raw', 'decoded'];

    public const array VIEWS = ['grouped', 'flat'];

    public const int Q_MAX = 200;

    /** `group=` value for captures without a group key. */
    public const string UNGROUPED = 'ungrouped';

    public function __construct(
        public ?int $sel,
        public string $tab,
        public ?string $view,       // null: the stream picks its default
        public ?string $q,
        public ?string $group,
        public ?int $before,
    ) {}

    /** @param  array<string, mixed>  $query */
    public static function from(array $query): self
    {
        $string = fn (string $key): ?string => is_string($query[$key] ?? null) ? $query[$key] : null;
        $q = $string('q');
        $q = $q === null ? null : mb_substr(trim($q), 0, self::Q_MAX);
        $group = $string('group');
        $group = $group === null ? null : mb_substr(trim($group), 0, self::Q_MAX);

        return new self(
            sel: self::seq($string('sel')),
            tab: in_array($string('tab'), self::TABS, true) ? (string) $string('tab') : 'verdict',
            view: in_array($string('view'), self::VIEWS, true) ? $string('view') : null,
            q: $q === '' ? null : $q,
            group: $group === '' ? null : $group,
            before: self::seq($string('before')),
        );
    }

    /** `01`, `#01`, `1` → 1; anything else → null. */
    public static function seq(?string $value): ?int
    {
        if ($value === null || preg_match('/^#?(\d{1,9})$/', trim($value), $m) !== 1) {
            return null;
        }
        $seq = (int) $m[1];

        return $seq >= 1 ? $seq : null;
    }

    public function filtered(): bool
    {
        return $this->q !== null || $this->group !== null;
    }
}
