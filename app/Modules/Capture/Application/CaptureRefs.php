<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Persistence\Bytea;
use App\Modules\Capture\Domain\Capture;
use App\Modules\Capture\Events\CaptureFetchedByExecutor;
use App\Modules\Packs\Application\PackCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Captures as Proving and the executors see them (domain §1.1 "Capture →
 * Proving": referenced by id and body SHA-256, never mutated). The executor
 * fetch is the only path that hands out bytes, and Proving calls it only
 * after checking the job (TM-35); each fetch is logged per executor.
 */
final readonly class CaptureRefs
{
    public function __construct(private Clock $clock, private PackCatalog $packs) {}

    /**
     * The captures `#seq` of a project, in the order asked; unknown numbers are absent.
     *
     * @param  list<int>  $seqs
     * @return list<array{id: string, publicId: string, seq: int, bodySha256: string, provider: string|null, providerName: string|null, eventType: string|null, bodyKept: bool, bodySize: int, receivedAt: string, signature: string}>
     */
    public function forSeqs(string $projectId, array $seqs): array
    {
        if ($seqs === []) {
            return [];
        }
        $rows = Capture::query()->where('project_id', $projectId)->whereIn('seq', $seqs)
            ->get(['id', 'seq', 'body_sha256', 'provider', 'event_type', 'body_kept', 'body_size', 'received_at', 'sig_state'])
            ->keyBy('seq');
        $out = [];
        foreach ($seqs as $seq) {
            $c = $rows->get($seq);
            if ($c instanceof Capture) {
                $provider = $c->getAttribute('provider');
                $eventType = $c->getAttribute('event_type');
                $sig = $c->getAttribute('sig_state');
                $out[] = [
                    'id' => $c->id,
                    'publicId' => $c->publicId(),
                    'seq' => $c->seq,
                    'bodySha256' => bin2hex($c->body_sha256),
                    'provider' => is_string($provider) ? $provider : null,
                    'providerName' => $this->packs->providerName(is_string($provider) ? $provider : null),
                    'eventType' => is_string($eventType) ? $eventType : null,
                    'bodyKept' => $c->body_kept,
                    'bodySize' => $c->body_size,
                    'receivedAt' => $c->received_at->toIso8601ZuluString('millisecond'),
                    'signature' => is_string($sig) ? $sig : 'not_checked',
                ];
            }
        }

        return $out;
    }

    /**
     * The first captures of a group, oldest first (S-10 `?group=`).
     *
     * @return list<int>
     */
    public function seqsOfGroup(string $projectId, string $groupKey, int $limit): array
    {
        return array_values(array_map('intval', Capture::query()->where('project_id', $projectId)->where('group_key', $groupKey)
            ->orderBy('seq')->limit($limit)->pluck('seq')->all()));
    }

    /**
     * Capture numbers of stored ids (a run keeps `source_seq` for display
     * after retention deletes the capture).
     *
     * @param  list<string>  $ids
     * @return array<string, int> id => seq
     */
    public function seqsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<string, int> */
        return Capture::query()->whereIn('id', $ids)->pluck('seq', 'id')->map(fn (mixed $s): int => (int) $s)->all();
    }

    /**
     * The stored request of a capture for an executor that Proving has
     * checked holds a live job referencing it (TM-35). Logs the access.
     * Null when the capture or its kept body no longer exists.
     *
     * @return array{capture_id: string, seq: int, method: string, raw_target: string, headers: list<array{0: string, 1: string}>, redacted_headers: list<string>, content_type: string|null, body_base64: string, body_sha256: string, body_bytes: int}|null
     */
    public function requestForExecutor(string $captureId, string $jobId, ?string $agentId, ?string $runnerId): ?array
    {
        $capture = Capture::query()->find($captureId);
        if ($capture === null || ! $capture->body_kept) {
            return null;
        }
        $body = Bytea::read(DB::table('capture_bodies')->where('capture_id', $captureId)->value('body'));
        if ($body === null || strlen($body) > (int) config('agent.limits.capture_body')) {
            return null;
        }

        DB::table('capture_access_log')->insert([
            'capture_id' => $captureId,
            'job_id' => $jobId,
            'agent_id' => $agentId,
            'runner_id' => $runnerId,
            'at' => $this->clock->now(),
        ]);
        CaptureFetchedByExecutor::dispatch($captureId, $jobId, $agentId, $runnerId);

        return [
            'capture_id' => EntityId::encode(Capture::ID_PREFIX, $capture->id),
            'seq' => $capture->seq,
            'method' => $capture->method,
            'raw_target' => $capture->raw_target,
            'headers' => $capture->headers,
            'redacted_headers' => $capture->redactedHeaders(),
            'content_type' => $capture->content_type,
            'body_base64' => base64_encode($body),
            'body_sha256' => bin2hex($capture->body_sha256),
            'body_bytes' => strlen($body),
        ];
    }
}
