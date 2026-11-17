<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Persistence\Bytea;
use App\Modules\Capture\Domain\Capture;
use App\Modules\Packs\Application\PackCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Fixture download for one capture (issue #15, docs reference/fixtures): a
 * file in the format `webhooktrap test` reads, with the kept body byte for
 * byte. Headers follow the SCR-3 allow-list (Content-Type, User-Agent and
 * the pack's event headers); signature headers are never written, since
 * fixtures are re-signed at replay (SCR-5). The body is not scrubbed: the
 * summary names the secret patterns it contains so the page warns first.
 * Nothing is stored; the agent's scrubbed "Save as fixtures" comes later.
 */
final readonly class CaptureFixtures
{
    /** The agent's fixture body cap (agent/internal/fixture: 5 MiB). */
    public const int MAX_BODY = 5 << 20;

    private const array BASE_HEADERS = ['content-type', 'user-agent'];

    /** SCR-1 blocked secret patterns: label → regex. Only labels leave this class. */
    private const array SECRET_PATTERNS = [
        'sk_live_' => '/sk_live_[A-Za-z0-9]/',
        'sk_test_' => '/sk_test_[A-Za-z0-9]/',
        'rk_' => '/\brk_(?:live|test)_[A-Za-z0-9]/',
        'whsec_' => '/whsec_[A-Za-z0-9]/',
        'ghp_' => '/ghp_[A-Za-z0-9]/',
        'gho_' => '/gho_[A-Za-z0-9]/',
        'github_pat_' => '/github_pat_[A-Za-z0-9]/',
        'shpat_' => '/shpat_[A-Za-z0-9]/',
        'shpss_' => '/shpss_[A-Za-z0-9]/',
        'Slack token' => '/xox[abpr]-[A-Za-z0-9]/',
        'AWS key' => '/AKIA[0-9A-Z]{16}/',
        'JWT' => '/eyJ[A-Za-z0-9_-]{4,}\.eyJ[A-Za-z0-9_-]{4,}/',
        'Private key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    ];

    public function __construct(private PackCatalog $packs) {}

    /**
     * What the delivery page shows next to "Download fixture".
     *
     * @return array{available: bool, reason: string|null, path: string|null, secrets: list<string>}
     */
    public function summary(Capture $capture, ?string $body): array
    {
        $reason = $this->unavailable($capture, $body);
        if ($reason !== null || $body === null) {
            return ['available' => false, 'reason' => $reason ?? 'body_not_kept', 'path' => null, 'secrets' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'path' => $this->path($capture),
            'secrets' => self::secrets($body),
        ];
    }

    /**
     * The fixture file of capture `#seq`, or null when it has none.
     *
     * @return array{filename: string, json: string}|null
     */
    public function file(string $projectId, int $seq): ?array
    {
        $capture = Capture::query()->where('project_id', $projectId)->where('seq', $seq)->first();
        if ($capture === null) {
            return null;
        }
        $body = $capture->body_kept
            ? Bytea::read(DB::table('capture_bodies')->where('capture_id', $capture->id)->value('body'))
            : null;
        if ($body === null || $this->unavailable($capture, $body) !== null) {
            return null;
        }

        $attributes = $capture->getAttributes();
        $pack = is_string($attributes['derived_with_pack'] ?? null) ? $attributes['derived_with_pack'] : null;
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $source = sprintf(
            '%s %s, received %s%s',
            is_string($host) && $host !== '' ? $host : 'webhooktrap',
            $capture->publicId(),
            $capture->received_at->utc()->format('Y-m-d\TH:i:s\Z'),
            $pack !== null ? ', pack '.$pack : '',
        );

        $file = [
            'provider' => (string) $attributes['provider'],
            'event_type' => is_string($attributes['event_type'] ?? null) ? $attributes['event_type'] : '',
            'capture_order' => $capture->seq,
            'source' => $source,
            'headers' => $this->headers($capture),
            'signature' => 'resign',
            'body_sha256' => hash('sha256', $body),
            'body' => $body,
        ];

        $json = json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

        return ['filename' => basename($this->path($capture)), 'json' => $json];
    }

    /** Why capture has no fixture: null when it has one. */
    private function unavailable(Capture $capture, ?string $body): ?string
    {
        $provider = $capture->getAttributes()['provider'] ?? null;

        return match (true) {
            ! $capture->body_kept || $body === null => 'body_not_kept',
            ! is_string($provider) || $provider === '' => 'no_provider',
            strlen($body) > self::MAX_BODY => 'too_large',
            ! mb_check_encoding($body, 'UTF-8') => 'not_text',
            default => null,
        };
    }

    /** `webhooktrap/fixtures/<provider>/<event type>.<seq>.json` (NAME-2, agent path rule). */
    private function path(Capture $capture): string
    {
        $attributes = $capture->getAttributes();
        $safe = fn (string $s): string => trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '_', $s), '._') ?: 'event';
        $type = is_string($attributes['event_type'] ?? null) ? $safe($attributes['event_type']) : 'event';

        return sprintf('webhooktrap/fixtures/%s/%s.%02d.json', $safe((string) $attributes['provider']), $type, $capture->seq);
    }

    /** @return list<array{0: string, 1: string}> */
    private function headers(Capture $capture): array
    {
        $provider = $capture->getAttributes()['provider'] ?? null;
        $allowed = [...self::BASE_HEADERS, ...$this->packs->factHeaders(is_string($provider) ? $provider : null)];
        $redacted = array_map(strtolower(...), $capture->redactedHeaders());

        $out = [];
        foreach ($capture->headers as [$name, $value]) {
            $lower = strtolower($name);
            if (in_array($lower, $allowed, true) && ! in_array($lower, $redacted, true) && ! str_contains($lower, 'signature')) {
                $out[] = [$name, $value];
            }
        }

        return $out;
    }

    /** @return list<string> labels of the secret patterns found */
    private static function secrets(string $body): array
    {
        $found = [];
        foreach (self::SECRET_PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $body) === 1) {
                $found[] = $label;
            }
        }

        return $found;
    }
}
