<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Cache\ShortLivedCache;
use App\Modules\Capture\Domain\CaptureUrl;
use App\Modules\Capture\Domain\ResolvedKey;
use App\Modules\Identity\Application\PrincipalLiveness;
use App\Modules\Workspace\Application\ProjectOwners;

/**
 * Resolves a capture key for ingest (domain §4 "resolve key (cache)"). The
 * resolution, owner liveness included, is cached for a few seconds at most
 * (`capture.resolve_cache_seconds`) under a hash of the key, and evicted on
 * rotation, project deletion, claim and account deletion (DL-2). A cache
 * outage is a miss: ingest then reads the database.
 */
final readonly class CaptureKeys
{
    public function __construct(
        private ShortLivedCache $cache,
        private ProjectOwners $owners,
        private PrincipalLiveness $liveness,
    ) {}

    public function resolve(string $key): ResolvedKey
    {
        if (preg_match(CaptureUrl::KEY_PATTERN, $key) !== 1) {
            return ResolvedKey::unknown();
        }
        $cacheKey = self::cacheKey($key);
        if (($cached = ResolvedKey::fromCache($this->cache->get($cacheKey))) !== null) {
            return $cached;
        }

        $resolved = $this->lookup($key);
        $this->cache->put($cacheKey, $resolved->toCache(), max(1, (int) config('capture.resolve_cache_seconds')));

        return $resolved;
    }

    /**
     * Evicts every key (active and rotated) of these projects.
     *
     * @param  list<string>  $projectIds
     */
    public function evictProjects(array $projectIds): void
    {
        if ($projectIds === []) {
            return;
        }
        foreach (CaptureUrl::query()->whereIn('project_id', $projectIds)->pluck('capture_key') as $key) {
            $this->evict((string) $key);
        }
    }

    public function evict(string $key): void
    {
        $this->cache->forget(self::cacheKey($key));
    }

    private function lookup(string $key): ResolvedKey
    {
        $url = CaptureUrl::query()->where('capture_key', $key)->first(['id', 'project_id', 'state']);
        if ($url === null) {
            return ResolvedKey::unknown();
        }
        $owner = $this->owners->ownerOf($url->project_id);

        return new ResolvedKey(
            urlId: $url->id,
            projectId: $url->project_id,
            active: $url->isActive(),
            ownerKind: $owner?->ownerKind,
            ownerId: $owner?->ownerId,
            ownerLive: $owner !== null && $this->liveness->isLive($owner->ownerKind, $owner->ownerId),
        );
    }

    /** The key itself never appears in the cache store. */
    private static function cacheKey(string $key): string
    {
        return 'capture-key:'.hash('sha256', $key);
    }
}
