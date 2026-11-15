<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use Illuminate\Support\Facades\Log;

/**
 * Which agent builds this server talks to (R55; A09 decision 3 of the agent
 * connection review). Release builds below `min_agent_version` get 426.
 * A `0.0.0-dev` build has no known provenance: refused too, unless the
 * `AGENT_ALLOW_DEV_BUILDS` switch is on *and* the app runs in `local` or
 * `testing`; each accepted dev handshake logs a warning. Hosted and
 * production self-host therefore always refuse it.
 */
final class AgentVersions
{
    public const string DEV = '0.0.0-dev';

    public static function refused(string $version, string $handshake): bool
    {
        if ($version === self::DEV) {
            if (self::devBuildsAllowed()) {
                Log::warning('Agent dev build accepted', ['handshake' => $handshake]);

                return false;
            }

            return true;
        }

        return TerminalText::versionBelow($version, (string) config('agent.min_agent_version'));
    }

    public static function devBuildsAllowed(): bool
    {
        return config('agent.allow_dev_builds') === true && app()->environment(['local', 'testing']);
    }

    /**
     * `server.kind` in the pair and hello answers (A09 decision 6): the
     * client refuses `--insecure-dev-origin` against `hosted`. A server
     * running with `APP_ENV=local` reports `selfhost`, so a development
     * server on plain http:// can still be paired.
     */
    public static function serverKind(): string
    {
        return app()->environment('local') || config('identity.instance') === 'selfhost' ? 'selfhost' : 'hosted';
    }
}
