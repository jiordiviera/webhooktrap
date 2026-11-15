<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The request facts of a capture (domain §4; ADR-005 amendment 1 §1–§3,
 * §9.4), read once from the request and never changed after insert.
 *
 * - `method` and `raw_target` as received (`REQUEST_METHOD`, `REQUEST_URI`),
 *   never the `_method` / `X-HTTP-Method-Override` overrides.
 * - Headers: an ordered list of `[name, value]` pairs in the order the SAPI
 *   reports them (`header_fidelity = sapi`, PO-7). Credential header values
 *   (`REDACTED`) become `[redacted · N bytes]` (S-7). Headers our own
 *   trusted proxy added are removed and listed.
 * - Trust (SV-2): forwarded headers count only when the request came through
 *   a trusted proxy: mode `secret` (the image Caddyfile set `X-WT-Verified`),
 *   mode `ip` (peer in TRUSTED_PROXIES), never in mode `none`. `source_ip`
 *   comes from that chain only (TM-05); otherwise it is the TCP peer.
 * - `public_origin` from configuration (S-6); the scheme from a trusted
 *   `X-Forwarded-Proto` only when CAPTURE_TRUST_FORWARDED_ORIGIN is on.
 */
final readonly class RequestFacts
{
    /**
     * Values stored in place of these headers' values (S-7; A09 slice 3
     * review, decision 3): exact names, matched case-insensitively; lower
     * case here. Names are added by A09 review only, never by pack data.
     * Signature headers (HMAC outputs, needed for SIG-1) are kept.
     */
    public const array REDACTED = [
        'authorization', 'proxy-authorization', 'cookie',
        'x-gitlab-token', 'x-telegram-bot-api-secret-token', 'x-hook-secret',
        'x-api-key', 'api-key', 'x-auth-token',
    ];

    /** Observed forwarding facts (stored as observed, never trusted by themselves). */
    public const array FORWARDING = ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host', 'forwarded', 'x-real-ip'];

    /** Our own proof headers: never stored, whoever sent them. */
    public const array INTERNAL = ['x-wt-edge', 'x-wt-verified'];

    /**
     * @param  list<array{0: string, 1: string}>  $headers
     * @param  list<string>  $redactedHeaders
     * @param  array{trusted: bool, observed: list<array{0: string, 1: string}>, removed: list<string>}|null  $forwarded
     */
    public function __construct(
        public string $method,
        public string $rawTarget,
        public string $path,
        public ?string $rawQuery,
        public array $headers,
        public array $redactedHeaders,
        public string $publicOrigin,
        public string $publicOriginSource,
        public ?array $forwarded,
        public string $sourceIp,
        public ?string $contentType,
    ) {}

    public static function from(Request $request): self
    {
        $server = $request->server;
        $rawTarget = self::text((string) $server->get('REQUEST_URI', '/'));
        $cut = strpos($rawTarget, '?');
        $pairs = self::sapiHeaders($request);

        $trusted = self::fromTrustedProxy($request);
        $peer = (string) $server->get('REMOTE_ADDR', '');

        $observed = [];
        foreach ($pairs as [$name, $value]) {
            if (in_array(strtolower($name), self::FORWARDING, true)) {
                $observed[] = [$name, $value];
            }
        }

        $removable = $trusted ? array_map('strtolower', (array) config('capture.proxy_headers')) : [];
        $headers = [];
        $redacted = [];
        $removed = [];
        foreach ($pairs as [$name, $value]) {
            $lower = strtolower($name);
            if (in_array($lower, self::INTERNAL, true) || in_array($lower, $removable, true)) {
                $removed[] = $name;

                continue;
            }
            if (in_array($lower, self::REDACTED, true)) {
                $value = '[redacted · '.strlen($value).' bytes]';
                $redacted[] = $name;
            }
            $headers[] = [$name, self::text($value)];
        }

        [$origin, $originSource] = self::publicOrigin($request, $trusted);
        $contentType = $server->get('CONTENT_TYPE', $server->get('HTTP_CONTENT_TYPE'));

        return new self(
            method: (string) $server->get('REQUEST_METHOD', 'GET'),
            rawTarget: $rawTarget,
            path: $cut === false ? $rawTarget : substr($rawTarget, 0, $cut),
            rawQuery: $cut === false ? null : substr($rawTarget, $cut + 1),
            headers: $headers,
            redactedHeaders: array_values(array_unique($redacted)),
            publicOrigin: $origin,
            publicOriginSource: $originSource,
            forwarded: $observed === [] && $removed === [] ? null : [
                'trusted' => $trusted,
                'observed' => array_map(fn (array $p): array => [$p[0], self::text($p[1])], $observed),
                'removed' => array_values(array_unique($removed)),
            ],
            sourceIp: self::sourceIp($request, $trusted, $peer),
            contentType: is_string($contentType) && $contentType !== '' ? self::text($contentType) : null,
        );
    }

    /** Whether forwarded headers on this request were set by our own proxy (SV-2). */
    public static function fromTrustedProxy(Request $request): bool
    {
        return match ((string) config('capture.proxy_trust_mode')) {
            'secret' => $request->server->get('HTTP_X_WT_VERIFIED') === '1',
            'ip' => IpUtils::checkIp((string) $request->server->get('REMOTE_ADDR', ''), (array) config('capture.trusted_proxies')),
            default => false,
        };
    }

    /**
     * Client address through the trusted-proxy chain only (TM-05): the
     * right-most `X-Forwarded-For` entry that is not itself a trusted proxy.
     * The hosted proxy overwrites the header with the client address.
     */
    public static function sourceIp(Request $request, bool $trusted, string $peer): string
    {
        if ($trusted) {
            $chain = array_reverse(array_map('trim', explode(',', (string) $request->server->get('HTTP_X_FORWARDED_FOR', ''))));
            $proxies = config('capture.proxy_trust_mode') === 'ip' ? (array) config('capture.trusted_proxies') : [];
            foreach ($chain as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                    break;
                }
                if ($proxies !== [] && IpUtils::checkIp($candidate, $proxies)) {
                    continue;
                }

                return $candidate;
            }
        }

        return filter_var($peer, FILTER_VALIDATE_IP) !== false ? $peer : '0.0.0.0';
    }

    /**
     * Headers in the order the SAPI lists them (`HTTP_*`, plus the CGI
     * `CONTENT_TYPE` / `CONTENT_LENGTH`), names rebuilt from the variable
     * name. `Authorization` is added from the PHP auth variables when the
     * SAPI moved it there.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function sapiHeaders(Request $request): array
    {
        $pairs = [];
        $hasAuthorization = false;
        foreach ($request->server->all() as $key => $value) {
            $key = (string) $key;
            if (str_starts_with($key, 'HTTP_')) {
                $name = substr($key, 5);
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true) && $value !== '') {
                $name = $key;
            } else {
                continue;
            }
            $name = ucwords(strtolower(str_replace('_', '-', $name)), '-');
            $hasAuthorization = $hasAuthorization || $name === 'Authorization';
            $pairs[] = [$name, is_scalar($value) ? (string) $value : ''];
        }
        if (! $hasAuthorization && ($auth = $request->headers->get('authorization')) !== null) {
            $pairs[] = ['Authorization', $auth];
        }

        return $pairs;
    }

    /** @return array{0: string, 1: 'configured'|'forwarded_trusted'|'direct'} */
    private static function publicOrigin(Request $request, bool $trusted): array
    {
        $configured = config('capture.public_origin');
        $configured = is_string($configured) && $configured !== '' ? rtrim($configured, '/') : null;
        $proto = strtolower(trim((string) $request->server->get('HTTP_X_FORWARDED_PROTO', '')));

        if ($trusted && (bool) config('capture.trust_forwarded_origin') && in_array($proto, ['http', 'https'], true)) {
            $authority = $configured !== null
                ? (string) preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $configured)
                : self::directHost($request);

            return [$proto.'://'.$authority, 'forwarded_trusted'];
        }
        if ($configured !== null) {
            return [$configured, 'configured'];
        }

        $https = strtolower((string) $request->server->get('HTTPS', ''));

        return [self::text((in_array($https, ['on', '1'], true) ? 'https' : 'http').'://'.self::directHost($request)), 'direct'];
    }

    /** The `Host` the peer sent, read raw (Symfony's trusted-proxy settings never apply here). */
    private static function directHost(Request $request): string
    {
        return self::text((string) $request->server->get('HTTP_HOST', $request->server->get('SERVER_NAME', 'localhost')));
    }

    /**
     * PostgreSQL `text` and `jsonb` hold valid UTF-8 without NUL. Header
     * bytes outside that (obs-text) are replaced by U+FFFD; the raw target is
     * ASCII as received from the server (§9.3).
     */
    private static function text(string $value): string
    {
        $value = str_replace("\0", "\u{FFFD}", $value);

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_scrub($value, 'UTF-8');
    }
}
