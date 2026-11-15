<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\IdGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Append-only writer for `audit_log` (schema.md §7). Shared by every module;
 * holds no business rule. Never pass codes, token values or secrets: secret
 * fields are written as "[set]" by the caller.
 */
final readonly class AuditLog
{
    public function __construct(private Clock $clock, private IdGenerator $ids) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?string $subjectUserId = null,
        ?string $subject = null,
        ?string $actorUserId = null,
        ?string $ipPrefix = null,
        ?string $uaLabel = null,
        ?array $before = null,
        ?array $after = null,
        ?string $emailHmac = null,
    ): void {
        DB::table('audit_log')->insert([
            'id' => $this->ids->uuid(),
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject' => $subject,
            'email_hmac' => $emailHmac === null ? null : Bytea::bind($emailHmac),
            'subject_user_id' => $subjectUserId,
            'ip_prefix' => $ipPrefix,
            'ua_label' => $uaLabel,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'at' => $this->clock->now(),
        ]);
    }

    /**
     * Account deletion purge (domain §2.10 cascade, "Audit"): replace email
     * addresses inside before/after of the rows about or by this user with
     * their masked form. The foreign keys then set the user columns NULL.
     */
    public function maskEmailsFor(string $userId): void
    {
        $rows = DB::table('audit_log')
            ->where(fn ($q) => $q->where('subject_user_id', $userId)->orWhere('actor_user_id', $userId))
            ->get(['id', 'before', 'after']);

        foreach ($rows as $row) {
            $update = [];
            foreach (['before', 'after'] as $column) {
                $value = $row->{$column};
                if (! is_string($value)) {
                    continue;
                }
                $masked = (string) preg_replace_callback(
                    '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
                    fn (array $m): string => EmailMask::mask($m[0]),
                    $value,
                );
                if ($masked !== $value) {
                    $update[$column] = $masked;
                }
            }
            if ($update !== []) {
                DB::table('audit_log')->where('id', $row->id)->update($update);
            }
        }
    }
}
