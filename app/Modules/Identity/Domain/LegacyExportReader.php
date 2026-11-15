<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use RuntimeException;

/**
 * Reads the 1.x `users.json` export (decision 41) without ever parsing the
 * password-hash fields (PO-H option B, domain §2.4): a small lexical scanner
 * walks the top-level array of objects and decodes only allow-listed keys;
 * every other value is skipped as raw bytes and never becomes a PHP value.
 */
final class LegacyExportReader
{
    private const array ALLOWED = [
        'email' => 'email',
        'name' => 'name', 'fullName' => 'name', 'full_name' => 'name', 'displayName' => 'name', 'display_name' => 'name',
        'createdAt' => 'created_at', 'created_at' => 'created_at',
    ];

    private string $json = '';

    private int $pos = 0;

    /** @return list<array{email: string|null, name: string|null, created_at: string|null}> */
    public function read(string $json): array
    {
        $this->json = $json;
        $this->pos = 0;
        $this->ws();
        // Accept either a top-level array or {"users": [...]}.
        if ($this->peek() === '{') {
            $this->pos++;
            $found = false;
            $users = [];
            while (true) {
                $this->ws();
                if ($this->peek() === '}') {
                    break;
                }
                $key = $this->string();
                $this->ws();
                $this->expect(':');
                $this->ws();
                if ($key === 'users' && $this->peek() === '[') {
                    $users = $this->users();
                    $found = true;
                } else {
                    $this->skipValue();
                }
                $this->ws();
                if ($this->peek() === ',') {
                    $this->pos++;
                }
            }

            return $found ? $users : throw new RuntimeException('No users array in the export.');
        }

        return $this->users();
    }

    /** @return list<array{email: string|null, name: string|null, created_at: string|null}> */
    private function users(): array
    {
        $this->expect('[');
        $out = [];
        while (true) {
            $this->ws();
            if ($this->peek() === ']') {
                $this->pos++;
                break;
            }
            $out[] = $this->user();
            $this->ws();
            if ($this->peek() === ',') {
                $this->pos++;
            }
        }

        return $out;
    }

    /** @return array{email: string|null, name: string|null, created_at: string|null} */
    private function user(): array
    {
        $this->expect('{');
        $row = ['email' => null, 'name' => null, 'created_at' => null];
        while (true) {
            $this->ws();
            if ($this->peek() === '}') {
                $this->pos++;
                break;
            }
            $key = $this->string();
            $this->ws();
            $this->expect(':');
            $this->ws();
            $start = $this->pos;
            $this->skipValue();
            if (isset(self::ALLOWED[$key])) {
                $value = json_decode(substr($this->json, $start, $this->pos - $start), false, 4, JSON_THROW_ON_ERROR);
                $row[self::ALLOWED[$key]] = is_scalar($value) ? (string) $value : null;
            }
            $this->ws();
            if ($this->peek() === ',') {
                $this->pos++;
            }
        }

        return $row;
    }

    /** Advances past one JSON value without building it. */
    private function skipValue(): void
    {
        $c = $this->peek();
        if ($c === '"') {
            $this->skipString();

            return;
        }
        if ($c === '{' || $c === '[') {
            $depth = 0;
            $len = strlen($this->json);
            while ($this->pos < $len) {
                $ch = $this->json[$this->pos];
                if ($ch === '"') {
                    $this->skipString();

                    continue;
                }
                if ($ch === '{' || $ch === '[') {
                    $depth++;
                } elseif ($ch === '}' || $ch === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $this->pos++;

                        return;
                    }
                }
                $this->pos++;
            }
            throw new RuntimeException('Unterminated value in the export.');
        }
        if (preg_match('/\G(?:-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null)/', $this->json, $m, 0, $this->pos) !== 1) {
            throw new RuntimeException('Malformed value in the export at byte '.$this->pos.'.');
        }
        $this->pos += strlen($m[0]);
    }

    private function skipString(): void
    {
        $len = strlen($this->json);
        $this->pos++;
        while ($this->pos < $len) {
            $ch = $this->json[$this->pos];
            if ($ch === '\\') {
                $this->pos += 2;

                continue;
            }
            $this->pos++;
            if ($ch === '"') {
                return;
            }
        }
        throw new RuntimeException('Unterminated string in the export.');
    }

    private function string(): string
    {
        $start = $this->pos;
        if ($this->peek() !== '"') {
            throw new RuntimeException('Expected a key at byte '.$this->pos.'.');
        }
        $this->skipString();

        return (string) json_decode(substr($this->json, $start, $this->pos - $start), false, 2, JSON_THROW_ON_ERROR);
    }

    private function ws(): void
    {
        $this->pos += strspn($this->json, " \t\r\n", $this->pos);
    }

    private function peek(): string
    {
        return $this->json[$this->pos] ?? '';
    }

    private function expect(string $char): void
    {
        if ($this->peek() !== $char) {
            throw new RuntimeException("Expected '{$char}' at byte {$this->pos}.");
        }
        $this->pos++;
    }
}
