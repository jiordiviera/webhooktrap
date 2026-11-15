<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Cache\StoreFailures;
use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\IdGenerator;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\RateLimit\ClientAddress;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Capture\Domain\Dropped;
use App\Modules\Capture\Domain\ReadBody;
use App\Modules\Capture\Domain\RequestFacts;
use App\Modules\Capture\Domain\ResolvedKey;
use App\Modules\Capture\Events\CaptureDropped;
use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Identity\Application\GuestQuotas;
use App\Modules\Identity\Application\PrincipalLiveness;
use App\Modules\Retention\Application\GuestStorageBudget;
use App\Modules\Workspace\Application\ProjectCounters;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * The ingest pipeline (domain §4): edge rate limit → resolve key (cached,
 * DL-2) → read the body up to the hard limit and hash it → if storable,
 * insert capture and body in one transaction (quotas and `seq` inside) →
 * after commit, `CaptureReceived`.
 *
 * The caller answers every outcome but `rate_limited` and `too_large` with
 * the same bytes (ADR-005 amendment 1 §4); those two are network-level and
 * never depend on whether a capture key exists or on its state. The edge
 * bucket is the client address; from a known provider egress range it is
 * (address, SHA-256 of the key segment as sent), counted alike for unknown
 * and malformed keys (A09 S3-1).
 *
 * Quotas count `counted_bytes`: the kept body plus the variable-length
 * request facts stored with it (headers, target, origin, forwarding facts,
 * content type), so a body over the kept limit is not free (A09 S3-7).
 *
 * Persistence needs PostgreSQL only (ADR-004): the rate limiter and the key
 * cache fail open (failures reported at most once a minute, S3-11), and
 * events are raised after commit, outside it.
 */
final readonly class Ingest
{
    public const string STORED = 'stored';

    public const string DROPPED = 'dropped';

    public const string TOO_LARGE = 'too_large';

    public const string RATE_LIMITED = 'rate_limited';

    public function __construct(
        private Clock $clock,
        private IdGenerator $ids,
        private Throttle $throttle,
        private CaptureKeys $keys,
        private ProjectCounters $counters,
        private GuestQuotas $guestQuotas,
        private GuestStorageBudget $guestBudget,
        private PrincipalLiveness $liveness,
        private StoreFailures $failures,
    ) {}

    public function handle(Request $request, string $captureKey): string
    {
        $receivedAt = $this->clock->now();
        $facts = RequestFacts::from($request);
        if (! $this->withinEdgeLimit($facts->sourceIp, $captureKey)) {
            return self::RATE_LIMITED;
        }

        $hardMax = (int) config('capture.request_max_bytes');
        $declared = $request->server->get('CONTENT_LENGTH', $request->server->get('HTTP_CONTENT_LENGTH'));
        if (is_numeric($declared) && (int) $declared > $hardMax) {
            return self::TOO_LARGE;
        }

        $resolved = $this->keys->resolve($captureKey);

        // Read and hash the whole body whatever the key, so the 413 and the
        // work done do not depend on whether the key exists.
        $stream = $request->getContent(true);
        $body = ReadBody::from($stream, (int) config('capture.body_keep_max_bytes'), $hardMax);
        if ($body->tooLarge) {
            return self::TOO_LARGE;
        }

        if (($reason = $resolved->dropReason()) !== null) {
            return $this->drop($reason);
        }

        try {
            [$captureId, $seq] = $this->store($resolved, $facts, $body, $receivedAt);
        } catch (Dropped $dropped) {
            return $this->drop($dropped->reason);
        } catch (QueryException $e) {
            // S3-10: a lock wait passed `store_lock_timeout_ms` (55P03).
            if ($e->getCode() === '55P03') {
                $this->failures->report('ingest_lock_timeout', $e);
            } else {
                report($e);
            }

            return $this->drop('store_error');
        } catch (Throwable $e) {
            report($e);

            return $this->drop('store_error');
        }

        try {
            CaptureReceived::dispatch($captureId, (string) $resolved->projectId, $seq);
        } catch (Throwable $e) {
            report($e);
        }

        return self::STORED;
    }

    /** @return array{0: string, 1: int} [capture id, seq] */
    private function store(ResolvedKey $resolved, RequestFacts $facts, ReadBody $body, CarbonImmutable $receivedAt): array
    {
        $projectId = (string) $resolved->projectId;
        $ownerId = (string) $resolved->ownerId;
        $urlId = (string) $resolved->urlId;
        $captureId = $this->ids->uuid();
        $headers = json_encode($facts->headers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $forwarded = $facts->forwarded === null ? null
            : json_encode($facts->forwarded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bytes = self::countedBytes($facts, $body, $headers, $forwarded);

        $seq = DB::transaction(function () use ($resolved, $projectId, $ownerId, $urlId, $captureId, $facts, $body, $receivedAt, $headers, $forwarded, $bytes): int {
            // S3-10: no lock wait in here is unbounded.
            DB::statement('SET LOCAL lock_timeout = '.max(1, (int) config('capture.store_lock_timeout_ms')));

            $guest = $resolved->ownerKind === 'guest';
            if ($guest) {
                // Guarded update: also fails for an ended, claimed or expired
                // session, or one at its count or byte cap.
                if (! $this->guestQuotas->reserveCapture($ownerId, $bytes)) {
                    throw new Dropped($this->liveness->guestIsLive($ownerId) ? 'quota' : 'expired');
                }
            } else {
                // Takes the project row lock first: an account tombstone in
                // flight (which locks the same rows) commits before or after
                // this whole transaction, and the liveness read below sees it.
                if (! $this->counters->reserveCapture($projectId, $bytes)) {
                    throw new Dropped('account_quota');
                }
                if (! $this->liveness->userIsLive($ownerId)) {
                    throw new Dropped('expired');
                }
            }

            // S3-6: the key may have been rotated since it was resolved (a
            // cached resolution, or a slow body). `FOR SHARE` waits for a
            // rotation in flight and holds one off until this commits.
            $url = DB::selectOne('SELECT state FROM capture_urls WHERE id = ? FOR SHARE', [$urlId]);
            if ($url === null || $url->state !== 'active') {
                throw new Dropped('rotated');
            }

            try {
                $seq = $this->counters->nextCaptureSeq($projectId);
            } catch (RuntimeException) {
                throw new Dropped('expired');
            }

            // S3-10: the single global budget row is locked last, right
            // before the insert, so it is held as briefly as possible.
            if ($guest && ! $this->guestBudget->reserve($bytes)) {
                throw new Dropped('guest_budget');
            }

            DB::table('captures')->insert([
                'id' => $captureId,
                'project_id' => $projectId,
                'capture_url_id' => $urlId,
                'seq' => $seq,
                'received_at' => $receivedAt->format('Y-m-d H:i:s.uP'),
                'method' => $facts->method,
                'raw_target' => $facts->rawTarget,
                'path' => $facts->path,
                'raw_query' => $facts->rawQuery,
                'headers' => $headers,
                'redacted_headers' => self::textArray($facts->redactedHeaders),
                'header_fidelity' => 'sapi',
                'public_origin' => $facts->publicOrigin,
                'public_origin_source' => $facts->publicOriginSource,
                'forwarded' => $forwarded,
                'source_ip' => $facts->sourceIp,
                'content_type' => $facts->contentType,
                'body_size' => $body->size,
                'body_sha256' => Bytea::bind($body->sha256),
                'body_kept' => $body->kept !== null,
                'counted_bytes' => $bytes,
            ]);
            if ($body->kept !== null) {
                DB::table('capture_bodies')->insert(['capture_id' => $captureId, 'body' => Bytea::bind($body->kept)]);
            }

            return $seq;
        });

        return [$captureId, $seq];
    }

    /**
     * Bytes a capture counts against the quotas and the guest budget (S3-7):
     * the kept body and every variable-length request fact stored with it.
     */
    public static function countedBytes(RequestFacts $facts, ReadBody $body, string $headersJson, ?string $forwardedJson): int
    {
        return $body->storedBytes() + strlen($headersJson) + strlen($facts->rawTarget) + strlen($facts->path)
            + strlen($facts->rawQuery ?? '') + strlen($facts->publicOrigin) + strlen($forwardedJson ?? '')
            + strlen($facts->contentType ?? '');
    }

    private function withinEdgeLimit(string $sourceIp, string $captureKey): bool
    {
        $limit = (array) config('capture.edge_rate');
        $bucket = 'capture:edge:'.ClientAddress::rateKey($sourceIp);
        if (self::fromProviderRange($sourceIp)) {
            // Whatever the key: known, unknown or malformed alike (S3-1).
            $bucket .= ':key:'.hash('sha256', $captureKey);
        }
        try {
            return $this->throttle->reserve($bucket, (int) ($limit['max'] ?? 600), (int) ($limit['decay'] ?? 60));
        } catch (Throwable $e) {
            $this->failures->report('rate_limit', $e); // the limiter's store is down: fail open, keep capturing

            return true;
        }
    }

    /** Whether the address is in a documented provider egress range (`capture.edge_provider_ranges`). */
    public static function fromProviderRange(string $ip): bool
    {
        $ranges = [];
        foreach ((array) config('capture.edge_provider_ranges') as $list) {
            foreach ((array) $list as $cidr) {
                $ranges[] = (string) $cidr;
            }
        }

        return $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }

    private function drop(string $reason): string
    {
        try {
            CaptureDropped::dispatch($reason);
        } catch (Throwable $e) {
            report($e);
        }

        return self::DROPPED;
    }

    /** @param  list<string>  $values */
    private static function textArray(array $values): string
    {
        return '{'.implode(',', array_map(fn (string $v): string => '"'.addcslashes($v, '"\\').'"', $values)).'}';
    }
}
