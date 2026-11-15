<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Broadcasting\ChannelSigner;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Contracts\BrowserChannel;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * `POST /broadcasting/auth` (event-catalogue §1 rules 1, 2, 6; A08 spike C-3):
 * the browser endpoint, `web` and `guest` principals only. Laravel's
 * `Broadcast::routes()` is not used. Principal type first, then the owning
 * module's `BrowserChannel` check. Only `private-{family}.{uuid}` names are
 * accepted; public, presence and encrypted channels are refused. Every
 * denial is the same bare `403`. The answer signs `channel_data.user_id =
 * <user|guest>:<id>`.
 */
final readonly class BroadcastAuthController
{
    private const string CHANNEL_PATTERN = '/^private-([a-z]+)\.([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/';

    /** @param  iterable<BrowserChannel>  $channels */
    public function __construct(
        private GuestSessions $guests,
        private ChannelSigner $signer,
        private iterable $channels,
    ) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');
        if (! is_string($socketId) || ! is_string($channelName)
            || preg_match(ChannelSigner::SOCKET_ID_PATTERN, $socketId) !== 1
            || preg_match(self::CHANNEL_PATTERN, $channelName, $m) !== 1) {
            return self::denied();
        }

        $user = $request->user();
        if ($user instanceof User && ! $user->isVerified()) {
            return self::denied();
        }
        $principal = $this->guests->principal($request);
        if ($principal === null || ! in_array($principal->kind, ['user', 'guest'], true)) {
            return self::denied();
        }

        $channel = $this->channel($m[1]);
        if ($channel === null || ! $channel->allows($principal, $m[2])) {
            return self::denied();
        }

        try {
            return new JsonResponse($this->signer->sign($socketId, $channelName, $principal->kind.':'.$principal->id), 200, [
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (Throwable $e) {
            report($e);

            return self::denied();
        }
    }

    private function channel(string $name): ?BrowserChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->name() === $name) {
                return $channel;
            }
        }

        return null;
    }

    private static function denied(): Response
    {
        return new Response('', 403, ['Cache-Control' => 'no-store, private']);
    }
}
