<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Infrastructure\Clock\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The activation funnel (issue #18, decision 79): project created (for a
 * guest, the URL), first stored delivery, first run with a verdict, and a
 * guest session claimed by an account.
 *
 * First party and aggregate only. Each project keeps two timestamps so a
 * milestone counts once; the counts go to `activation_daily` as one row per
 * UTC day, milestone and guest flag, with the summed seconds since project
 * creation. No project, user, session, IP or user agent is ever written
 * there, and nothing is sent to a third party.
 */
final readonly class Activation
{
    public const string PROJECT_CREATED = 'project_created';

    public const string FIRST_CAPTURE = 'first_capture';

    public const string FIRST_RUN = 'first_run';

    public const string CLAIMED = 'claimed';

    /** Run statuses that reached a verdict (Proving `Run::FINAL` minus couldn't start and aborted). */
    private const array VERDICT_STATUSES = ['findings', 'inconclusive', 'passed'];

    public function __construct(private Clock $clock) {}

    public function projectCreated(string $projectId): void
    {
        if (! Str::isUuid($projectId)) {
            return;
        }
        $guest = DB::table('projects')->where('id', $projectId)->value('is_guest');

        if ($guest !== null) {
            $this->count(self::PROJECT_CREATED, (bool) $guest, 0);
        }
    }

    /** Called on every stored delivery: one indexed update, a count only the first time. */
    public function captureReceived(string $projectId): void
    {
        $this->first($projectId, 'first_capture_at', self::FIRST_CAPTURE);
    }

    public function runFinished(string $projectId, string $status): void
    {
        if (in_array($status, self::VERDICT_STATUSES, true)) {
            $this->first($projectId, 'first_run_at', self::FIRST_RUN);
        }
    }

    /** A guest session became an account's; an empty session (nothing to keep) is not counted. */
    public function claimed(bool $empty): void
    {
        if (! $empty) {
            $this->count(self::CLAIMED, true, 0);
        }
    }

    /**
     * Totals over the last `$days` UTC days, today included.
     *
     * @return array<string, array{guest: int, user: int, guestAvgSeconds: int|null}>
     */
    public function summary(int $days): array
    {
        $days = max(1, min(90, $days));
        $start = $this->clock->now()->utc()->startOfDay()->subDays($days - 1)->toDateString();
        $rows = DB::table('activation_daily')
            ->where('day', '>=', $start)
            ->groupBy('milestone', 'guest')
            ->selectRaw('milestone, guest, sum(count) AS n, sum(seconds_total) AS s')
            ->get();

        $out = [];

        foreach ([self::PROJECT_CREATED, self::FIRST_CAPTURE, self::FIRST_RUN, self::CLAIMED] as $m) {
            $out[$m] = ['guest' => 0, 'user' => 0, 'guestAvgSeconds' => null];
        }

        foreach ($rows as $row) {
            $m = (string) $row->milestone;

            if (! isset($out[$m])) {
                continue;
            }
            $n = (int) $row->n;

            if ($row->guest) {
                $out[$m]['guest'] = $n;
                $out[$m]['guestAvgSeconds'] = $n > 0 ? intdiv((int) $row->s, $n) : null;
            } else {
                $out[$m]['user'] = $n;
            }
        }

        return $out;
    }

    private function first(string $projectId, string $column, string $milestone): void
    {
        if (! Str::isUuid($projectId)) {
            return;
        }
        $now = $this->clock->now();
        $row = DB::selectOne(
            "UPDATE projects SET {$column} = ? WHERE id = ? AND {$column} IS NULL RETURNING is_guest, created_at",
            [$now, $projectId],
        );

        if ($row === null) {
            return;
        }
        $seconds = max(0, $now->getTimestamp() - strtotime((string) $row->created_at));
        $this->count($milestone, (bool) $row->is_guest, $seconds);
    }

    private function count(string $milestone, bool $guest, int $seconds): void
    {
        DB::statement(
            'INSERT INTO activation_daily (day, milestone, guest, count, seconds_total) VALUES (?, ?, ?, 1, ?)
             ON CONFLICT (day, milestone, guest)
             DO UPDATE SET count = activation_daily.count + 1, seconds_total = activation_daily.seconds_total + EXCLUDED.seconds_total',
            [$this->clock->now()->utc()->toDateString(), $milestone, $guest, $seconds],
        );
    }
}
