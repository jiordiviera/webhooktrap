<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contracts;

use Carbon\CarbonImmutable;

/**
 * Seam to the Capture module (event-catalogue §2 `ProjectCreated` → Capture
 * "create capture URL"; contracts §3.2 `POST /new` creates the capture URL in
 * the same transaction). Workspace calls `provision()` inside the project's
 * creation transaction; Capture implements this contract and binds it
 * (CaptureServiceProvider).
 */
interface ProjectCaptureUrls
{
    /** Inside the creation transaction: create the project's active capture URL. */
    public function provision(string $projectId): void;

    /** The full active capture URL (`https://<capture host>/c/<captureKey>`), or null. */
    public function activeUrl(string $projectId): ?string;

    /**
     * Stored captures per project and the newest `received_at` (S-15 `ProjectRow`).
     *
     * @param  list<string>  $projectIds
     * @return array<string, array{deliveries: int, lastActivityAt: CarbonImmutable|null}>
     */
    public function activity(array $projectIds): array;

    /**
     * Per project: the bytes its stored captures count against the quotas
     * (`counted_bytes`) and how many it stored on the UTC day `$day`
     * (`Y-m-d`). Projects without captures are absent. Used at claim, so a
     * claimed guest project's account counters start from what it holds
     * (A09 S3-9).
     *
     * @param  list<string>  $projectIds
     * @return array<string, array{bytes: int, onDay: int}>
     */
    public function storedUsage(array $projectIds, string $day): array;
}
