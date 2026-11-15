<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;

/**
 * Cross-process long-poll slots (TM-40, contracts §5.4 revision 6.3): a
 * fixed number of PostgreSQL session advisory locks. A slot is held by one
 * PHP process at a time and freed when it is released or when the holding
 * process (its database session) dies, so a crashed worker never leaks one.
 * Pools are named so agent and runner slots never mix. Keyed pools
 * (`acquireKeyed`, AC-5) give each key its own small pool, e.g. one slot per
 * agent and two per workspace; they use the single-bigint lock space, which
 * never conflicts with the two-int space of the shared pools. Session locks
 * need a session-pooled connection (never PgBouncer transaction mode).
 */
final readonly class LongPollSlots
{
    /** Advisory lock class id, 'WT'. */
    private const int LOCK_CLASS = 0x5754;

    public function __construct(private ConnectionInterface $db) {}

    /** Takes a free slot of the pool; null when every slot is held. */
    public function acquire(string $pool, int $slots): ?int
    {
        $base = $this->poolBase($pool);
        for ($i = 0; $i < max(0, $slots); $i++) {
            $row = $this->db->selectOne('SELECT pg_try_advisory_lock(?, ?) AS ok', [self::LOCK_CLASS, $base + $i]);
            if ($row !== null && ($row->ok ?? false) === true) {
                return $base + $i;
            }
        }

        return null;
    }

    public function release(int $slot): void
    {
        $this->db->selectOne('SELECT pg_advisory_unlock(?, ?) AS ok', [self::LOCK_CLASS, $slot]);
    }

    /** Takes one of `$slots` slots of a per-key pool (e.g. `agent:<id>`); null when all are held. */
    public function acquireKeyed(string $key, int $slots): ?int
    {
        for ($i = 0; $i < max(0, $slots); $i++) {
            $lock = self::keyedLock($key, $i);
            $row = $this->db->selectOne('SELECT pg_try_advisory_lock(?) AS ok', [$lock]);
            if ($row !== null && ($row->ok ?? false) === true) {
                return $lock;
            }
        }

        return null;
    }

    public function releaseKeyed(int $lock): void
    {
        $this->db->selectOne('SELECT pg_advisory_unlock(?) AS ok', [$lock]);
    }

    /**
     * The global slot count actually used: the configured value, never more
     * than half of the HTTP workers, and always leaving 2 workers free.
     */
    public static function cap(int $configured, int $workers): int
    {
        return max(1, min($configured, intdiv($workers, 2), $workers - 2));
    }

    /** A signed 64-bit lock id from the first 8 bytes of SHA-256('WT|' key '|' slot). */
    private static function keyedLock(string $key, int $slot): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('q', substr(hash('sha256', 'WT|'.$key.'|'.$slot, true), 0, 8));

        return $unpacked[1];
    }

    /** Pools get disjoint ranges of 1,000 lock ids. */
    private function poolBase(string $pool): int
    {
        return (crc32($pool) % 1000) * 1000;
    }
}
