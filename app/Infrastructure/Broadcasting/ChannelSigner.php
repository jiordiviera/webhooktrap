<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use RuntimeException;

/**
 * Pusher-protocol subscription signature for Reverb (event-catalogue §1 rule
 * 6, A08 spike C-3). The principal is signed into `channel_data` on private
 * channels too, so Reverb knows whose socket it is (per-principal cap,
 * lifetime, `terminate_connections`). The signature covers
 * `socket_id:channel:channel_data`.
 *
 * Knows nothing of who may subscribe: the auth endpoint decides that first.
 */
final readonly class ChannelSigner
{
    public const string SOCKET_ID_PATTERN = '/^\d{1,20}\.\d{1,20}$/';

    /**
     * @param  string  $principalKey  `<type>:<id>`, e.g. `user:0193…`
     * @return array{auth: string, channel_data: string}
     */
    public function sign(string $socketId, string $channel, string $principalKey): array
    {
        if (preg_match(self::SOCKET_ID_PATTERN, $socketId) !== 1) {
            throw new RuntimeException('Malformed socket id.');
        }
        [$key, $secret] = $this->credentials();
        $channelData = json_encode(['user_id' => $principalKey], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $socketId.':'.$channel.':'.$channelData, $secret);

        return ['auth' => $key.':'.$signature, 'channel_data' => $channelData];
    }

    /** @return array{0: string, 1: string} */
    private function credentials(): array
    {
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');
        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            throw new RuntimeException('Reverb app credentials are not configured.');
        }

        return [$key, $secret];
    }
}
