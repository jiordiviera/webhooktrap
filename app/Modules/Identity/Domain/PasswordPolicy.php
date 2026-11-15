<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Normalizer;
use RuntimeException;

/**
 * Password policy for every path (domain §2.1): 12 to 128 code points after
 * NFC, any printable characters, never truncated, not the account email, not
 * in the bundled common-password list. No network call.
 */
final readonly class PasswordPolicy
{
    public function __construct(
        private int $min,
        private int $max,
        private string $commonListPath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('identity.password.min'),
            (int) config('identity.password.max'),
            (string) config('identity.password.common_list'),
        );
    }

    /** The first error code, or null when the password is acceptable. */
    public function violation(string $password, ?string $email): ?string
    {
        $normalised = Normalizer::normalize($password, Normalizer::FORM_C);
        $normalised = is_string($normalised) ? $normalised : $password;
        $length = mb_strlen($normalised, 'UTF-8');

        if ($length < $this->min) {
            return 'password_too_short';
        }
        if ($length > $this->max) {
            return 'password_too_long';
        }
        if ($email !== null && mb_strtolower($normalised) === mb_strtolower(trim($email))) {
            return 'password_is_email';
        }
        if ($this->isCommon(mb_strtolower($normalised))) {
            return 'password_common';
        }

        return null;
    }

    /** @return array{minLength: int, maxLength: int, unit: string} contracts §3.4 PasswordPolicy */
    public function toProp(): array
    {
        return ['minLength' => $this->min, 'maxLength' => $this->max, 'unit' => 'code_points'];
    }

    /** The list file exists and holds at least one entry (boot check, A09 F6). */
    public function listIsAvailable(): bool
    {
        $list = $this->readList();

        return $list !== null && self::hasEntries($list);
    }

    /**
     * Membership in the bundled list (about 100 000 entries, 1.4 MB, already
     * NFC and lower-cased). The file is read and searched as one string per
     * check (~1.5 MB transient, about a millisecond) instead of being kept in
     * process memory: no per-worker state (FrankenPHP worker mode) and it
     * only runs on the few routes that set a password. A missing or empty
     * list fails closed: every password is refused (A09 F6).
     */
    private function isCommon(string $candidate): bool
    {
        $list = $this->readList();
        if ($list === null || ! self::hasEntries($list)) {
            report(new RuntimeException('Common-password list unavailable: refusing passwords (fail closed).'));

            return true;
        }

        return str_contains("\n".str_replace("\r\n", "\n", $list)."\n", "\n{$candidate}\n");
    }

    private static function hasEntries(string $list): bool
    {
        return preg_match('/^[^#\r\n]/m', $list) === 1;
    }

    private function readList(): ?string
    {
        if (! is_file($this->commonListPath) || ! is_readable($this->commonListPath)) {
            return null;
        }
        $list = file_get_contents($this->commonListPath);

        return is_string($list) ? $list : null;
    }
}
