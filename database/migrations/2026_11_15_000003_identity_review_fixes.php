<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A09 slice 1 review: sessions revoked when ownership of the email is first
 * proven elsewhere (F1), and a pending recovery link closed by an email
 * change (F12).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_sessions DROP CONSTRAINT user_sessions_revoked_reason_check');
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_revoked_reason_check CHECK (revoked_reason IS NULL OR revoked_reason IN ('user','signed_out','password_changed','password_reset','recovery','email_changed','email_verified','admin','expired','account_deleted'))");
        DB::statement('ALTER TABLE account_recovery_requests DROP CONSTRAINT account_recovery_cancelled_by_check');
        DB::statement("ALTER TABLE account_recovery_requests ADD CONSTRAINT account_recovery_cancelled_by_check CHECK (cancelled_by IS NULL OR cancelled_by IN ('user','second_factor_signin','declined','link_expired','email_changed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_sessions DROP CONSTRAINT user_sessions_revoked_reason_check');
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_revoked_reason_check CHECK (revoked_reason IS NULL OR revoked_reason IN ('user','signed_out','password_changed','password_reset','recovery','email_changed','admin','expired','account_deleted'))");
        DB::statement('ALTER TABLE account_recovery_requests DROP CONSTRAINT account_recovery_cancelled_by_check');
        DB::statement("ALTER TABLE account_recovery_requests ADD CONSTRAINT account_recovery_cancelled_by_check CHECK (cancelled_by IS NULL OR cancelled_by IN ('user','second_factor_signin','declined','link_expired'))");
    }
};
