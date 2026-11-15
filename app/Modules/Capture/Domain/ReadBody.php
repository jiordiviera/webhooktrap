<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * The request body as read by ingest (ADR-005 amendment 1 §5): size and
 * SHA-256 always (the body is read fully, up to the hard limit, even when it
 * is not kept or the key is unknown); the bytes only up to the kept limit.
 */
final readonly class ReadBody
{
    public function __construct(
        public bool $tooLarge,
        public int $size,
        public string $sha256,   // 32 raw bytes
        public ?string $kept,     // null when over the kept limit
    ) {}

    /**
     * Streams the body in chunks: never more than the kept limit is held in
     * memory, and reading stops one byte past the hard limit.
     *
     * @param  resource  $stream
     */
    public static function from(mixed $stream, int $keepMax, int $hardMax): self
    {
        $hash = hash_init('sha256');
        $size = 0;
        $kept = '';
        $keeping = true;
        while (! feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $size += strlen($chunk);
            if ($size > $hardMax) {
                return new self(true, $size, '', null);
            }
            hash_update($hash, $chunk);
            if ($keeping) {
                if ($size <= $keepMax) {
                    $kept .= $chunk;
                } else {
                    $keeping = false;
                    $kept = '';
                }
            }
        }

        return new self(false, $size, hash_final($hash, true), $keeping ? $kept : null);
    }

    /** Bytes counted against storage quotas: the kept body only. */
    public function storedBytes(): int
    {
        return $this->kept === null ? 0 : strlen($this->kept);
    }
}
