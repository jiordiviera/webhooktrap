<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * The kept body as the inspector shows it (S-04 Raw tab; contracts §3.3
 * `CaptureDetail.body`). Text when the bytes are valid UTF-8 without NUL,
 * escaped for display (DisplayText); otherwise binary, sent as base64 for the
 * hex + ASCII view. Both are capped (`capture.inspector.*_max_bytes`); the
 * full exact bytes are the raw download.
 */
final readonly class InspectorBody
{
    public function __construct(
        public string $encoding,     // 'utf8' | 'base64'
        public string $text,
        public bool $truncated,
        public int $shownBytes,      // bytes of the original body covered by `text`
        public bool $escaped,        // hidden characters were replaced by `\u{…}`
    ) {}

    public static function isBinary(string $bytes): bool
    {
        return str_contains($bytes, "\0") || ! mb_check_encoding($bytes, 'UTF-8');
    }

    public static function from(string $bytes, int $textMax, int $binaryMax): self
    {
        if (self::isBinary($bytes)) {
            $shown = substr($bytes, 0, max(0, $binaryMax));

            return new self('base64', base64_encode($shown), strlen($bytes) > strlen($shown), strlen($shown), false);
        }

        // Cut on a character boundary so the shown text stays valid UTF-8.
        $shown = strlen($bytes) > $textMax ? mb_strcut($bytes, 0, max(0, $textMax), 'UTF-8') : $bytes;
        [$text, $escaped] = DisplayText::body($shown);

        return new self('utf8', $text, strlen($bytes) > strlen($shown), strlen($shown), $escaped);
    }

    /** @return array{encoding: string, text: string, truncated: bool, shownBytes: int, escaped: bool} */
    public function toProps(): array
    {
        return [
            'encoding' => $this->encoding,
            'text' => $this->text,
            'truncated' => $this->truncated,
            'shownBytes' => $this->shownBytes,
            'escaped' => $this->escaped,
        ];
    }
}
