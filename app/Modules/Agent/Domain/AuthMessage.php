<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

/**
 * The challenge message an agent signs (contracts §5.2, S-3, TM-33):
 * `"webhooktrap-agent-auth/v1" ‖ server_origin ‖ agent_id ‖ nonce ‖ expires_at`,
 * each part prefixed with its length as 2 bytes big-endian. The server
 * origin is part of the message, so a signature made for one origin never
 * verifies at another.
 */
final class AuthMessage
{
    public const string DOMAIN = 'webhooktrap-agent-auth/v1';

    public static function bytes(string $serverOrigin, string $agentId, string $nonce, string $expiresAt): string
    {
        $out = '';
        foreach ([self::DOMAIN, $serverOrigin, $agentId, $nonce, $expiresAt] as $part) {
            $out .= pack('n', strlen($part)).$part;
        }

        return $out;
    }

    /** Scheme + host + port, lowercase, default port omitted. */
    public static function origin(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? 'localhost'));
        $port = $parts['port'] ?? null;
        $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        return $scheme.'://'.$host.($port !== null && ! $default ? ':'.$port : '');
    }
}
