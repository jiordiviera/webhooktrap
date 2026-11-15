<?php

declare(strict_types=1);

namespace App\Modules\Packs\Domain;

use stdClass;

/**
 * A capture body read as JSON, within limits: bodies over `maxBytes`, deeper
 * than `maxDepth`, not valid UTF-8 or not JSON are not JSON. Objects stay
 * `stdClass` so `{}` and `[]` keep their types (SHP-1); integers too large
 * for PHP stay strings (Shopify and GitHub ids).
 */
final readonly class JsonBody
{
    private function __construct(public mixed $root) {}

    public static function parse(?string $body, int $maxBytes, int $maxDepth): ?self
    {
        if ($body === null || $body === '' || strlen($body) > $maxBytes) {
            return null;
        }
        // A UTF-8 BOM is not JSON (RFC 8259 §8.1); senders that add one are rare.
        $decoded = json_decode($body, false, max(1, $maxDepth), JSON_BIGINT_AS_STRING);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return new self($decoded);
    }

    /** Value at a dot path (`data.object.id`; a numeric segment indexes an array), or null. */
    public function at(string $path): mixed
    {
        $node = $this->root;
        foreach (explode('.', $path) as $segment) {
            if ($node instanceof stdClass) {
                if (! property_exists($node, $segment)) {
                    return null;
                }
                $node = $node->{$segment};
            } elseif (is_array($node) && ctype_digit($segment) && array_key_exists((int) $segment, $node)) {
                $node = $node[(int) $segment];
            } else {
                return null;
            }
        }

        return $node;
    }

    /**
     * GRP-4: the first field of `data` named `id` or ending in `_id`, in
     * document order, with a string or integer value.
     *
     * @return array{0: string, 1: string}|null field name, value
     */
    public function firstDataId(): ?array
    {
        $data = $this->at('data');
        if (! $data instanceof stdClass) {
            return null;
        }
        foreach (get_object_vars($data) as $field => $value) {
            $field = (string) $field;
            if (($field === 'id' || str_ends_with($field, '_id')) && (is_string($value) || is_int($value))) {
                return [$field, (string) $value];
            }
        }

        return null;
    }
}
