<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Capture\Domain\CaptureUrl;
use App\Modules\Capture\Events\CaptureUrlRotated;
use App\Modules\Workspace\Contracts\OwnedProject;
use App\Modules\Workspace\Contracts\ProjectCaptureUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Capture URLs of a project (domain §4, schema §4). Bound as Workspace's
 * `ProjectCaptureUrls`, so the URL is provisioned inside the project's
 * creation transaction (contracts §3.2 `POST /new`, `POST /projects/new`).
 */
final readonly class CaptureUrls implements ProjectCaptureUrls
{
    public function __construct(private Clock $clock, private CaptureKeys $keys) {}

    public function provision(string $projectId): void
    {
        CaptureUrl::query()->create([
            'project_id' => $projectId,
            'capture_key' => CaptureUrl::unusedKey(),
            'state' => 'active',
        ]);
    }

    public function activeUrl(string $projectId): ?string
    {
        $key = CaptureUrl::query()->where('project_id', $projectId)->where('state', 'active')->value('capture_key');

        return is_string($key) ? self::urlFor($key) : null;
    }

    /**
     * @param  list<string>  $projectIds
     * @return array<string, array{deliveries: int, lastActivityAt: CarbonImmutable|null}>
     */
    public function activity(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }
        $rows = DB::table('captures')->whereIn('project_id', $projectIds)->groupBy('project_id')
            ->selectRaw('project_id, count(*) AS deliveries, max(received_at) AS last_at')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->project_id] = [
                'deliveries' => (int) $row->deliveries,
                'lastActivityAt' => $row->last_at === null ? null : CarbonImmutable::parse((string) $row->last_at)->utc(),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $projectIds
     * @return array<string, array{bytes: int, onDay: int}>
     */
    public function storedUsage(array $projectIds, string $day): array
    {
        if ($projectIds === []) {
            return [];
        }
        $rows = DB::table('captures')->whereIn('project_id', $projectIds)->groupBy('project_id')
            ->selectRaw("project_id, sum(counted_bytes) AS bytes, count(*) FILTER (WHERE (received_at AT TIME ZONE 'UTC')::date = ?::date) AS on_day", [$day])
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->project_id] = ['bytes' => (int) $row->bytes, 'onDay' => (int) $row->on_day];
        }

        return $out;
    }

    /**
     * `POST /p/{projectKey}/settings/capture/rotate` (contracts §3.2): the
     * active URL becomes `rotated` (it keeps answering the ingest invariant
     * and stores nothing) and a new active URL replaces it, in one
     * transaction under the row lock. Returns the new URL.
     */
    public function rotate(OwnedProject $project): string
    {
        if ($project->isGuest) {
            throw new LogicException('A guest capture URL is not rotated.');
        }
        [$old, $new] = DB::transaction(function () use ($project): array {
            $old = CaptureUrl::query()->where('project_id', $project->id)->where('state', 'active')->lockForUpdate()->first();
            $old?->forceFill(['state' => 'rotated', 'rotated_at' => $this->clock->now()])->save();
            $new = CaptureUrl::query()->create([
                'project_id' => $project->id,
                'capture_key' => CaptureUrl::unusedKey(),
                'state' => 'active',
            ]);

            return [$old, $new];
        });

        if ($old !== null) {
            $this->keys->evict($old->capture_key);
        }
        CaptureUrlRotated::dispatch($project->id, $old?->id, $new->id);

        return self::urlFor($new->capture_key);
    }

    /** `<public origin>/c/<captureKey>`; the origin is CAPTURE_PUBLIC_ORIGIN, else APP_URL. */
    public static function urlFor(string $key): string
    {
        $origin = config('capture.public_origin');
        $origin = is_string($origin) && $origin !== '' ? $origin : (string) config('app.url');

        return rtrim($origin, '/').'/c/'.$key;
    }
}
