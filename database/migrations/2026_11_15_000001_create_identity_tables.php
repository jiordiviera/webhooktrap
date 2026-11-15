<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Identity tables (schema.md §2): factors, sessions, guest sessions and the
 * emailed-link request tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('provider');
            $table->text('provider_subject');
            $table->boolean('provider_email_verified');
            $table->text('provider_label');
            $table->timestampTz('linked_at');
            $table->text('linked_via');
            $table->timestampsTz();
            $table->unique(['provider', 'provider_subject']);
            $table->unique(['user_id', 'provider']);
        });
        DB::statement("ALTER TABLE user_identities ADD CONSTRAINT user_identities_provider_check CHECK (provider IN ('github','google'))");
        DB::statement("ALTER TABLE user_identities ADD CONSTRAINT user_identities_linked_via_check CHECK (linked_via IN ('signup','oauth_email_match','settings'))");

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('credential_id')->unique();
            $table->binary('public_key');
            $table->integer('alg');
            $table->bigInteger('sign_count')->default(0);
            $table->uuid('aaguid')->nullable();
            $table->boolean('discoverable');
            $table->boolean('uv_at_registration');
            $table->boolean('backup_eligible');
            $table->boolean('backup_state');
            $table->text('name');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('flagged_at')->nullable();
            $table->text('flag_cause')->nullable();
            $table->smallInteger('flag_count')->default(0);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE webauthn_credentials ADD COLUMN transports text[]');
        DB::statement('ALTER TABLE webauthn_credentials ADD CONSTRAINT webauthn_alg_check CHECK (alg IN (-7, -8, -257))');
        DB::statement('ALTER TABLE webauthn_credentials ADD CONSTRAINT webauthn_name_check CHECK (char_length(name) BETWEEN 1 AND 48)');
        DB::statement("ALTER TABLE webauthn_credentials ADD CONSTRAINT webauthn_flag_cause_check CHECK (flag_cause IS NULL OR flag_cause IN ('counter','user_handle_mismatch'))");
        DB::statement('ALTER TABLE webauthn_credentials ADD CONSTRAINT webauthn_flag_pair_check CHECK ((flagged_at IS NULL) = (flag_cause IS NULL))');

        Schema::create('totp_factors', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->binary('secret');
            $table->timestampTz('confirmed_at')->nullable();
            $table->bigInteger('last_used_step')->nullable();
            $table->timestampsTz();
        });

        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('code_hash');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('second_factor_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('failed_at');
            $table->text('kind');
            $table->index(['user_id', 'failed_at']);
        });
        DB::statement("ALTER TABLE second_factor_failures ADD CONSTRAINT second_factor_failures_kind_check CHECK (kind IN ('totp','recovery_code','webauthn'))");

        Schema::create('account_recovery_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('token_hash')->unique();
            $table->timestampTz('requested_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completes_at')->nullable();
            $table->timestampTz('wait_extended_at')->nullable();
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_by')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->binary('finish_token_hash')->nullable()->unique();
            $table->timestampTz('finish_token_expires_at')->nullable();
            $table->timestampTz('lock_until')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE account_recovery_requests ADD CONSTRAINT account_recovery_cancelled_by_check CHECK (cancelled_by IS NULL OR cancelled_by IN ('user','second_factor_signin','declined','link_expired'))");
        DB::statement('CREATE UNIQUE INDEX account_recovery_requests_one_open ON account_recovery_requests (user_id) WHERE cancelled_at IS NULL AND completed_at IS NULL');

        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('token_hash')->unique();
            $table->timestampTz('requested_at');
            $table->timestampTz('expires_at');
            $table->boolean('old_required');
            $table->binary('old_token_hash')->nullable()->unique();
            $table->timestampTz('new_confirmed_at')->nullable();
            $table->timestampTz('old_confirmed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE email_change_requests ADD COLUMN new_email citext NOT NULL');
        DB::statement("ALTER TABLE email_change_requests ADD CONSTRAINT email_change_cancelled_reason_check CHECK (cancelled_reason IS NULL OR cancelled_reason IN ('replaced','recovery','user','expired'))");
        DB::statement('CREATE UNIQUE INDEX email_change_requests_one_pending ON email_change_requests (user_id) WHERE confirmed_at IS NULL AND cancelled_at IS NULL');

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->binary('token_hash')->unique();
            $table->timestampTz('requested_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE account_deletion_requests ADD CONSTRAINT account_deletion_cancelled_reason_check CHECK (cancelled_reason IS NULL OR cancelled_reason IN ('replaced','recovery','user','email_changed','expired'))");
        DB::statement('CREATE UNIQUE INDEX account_deletion_requests_one_pending ON account_deletion_requests (user_id) WHERE used_at IS NULL AND cancelled_at IS NULL');

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('idle_expires_at');
            $table->timestampTz('absolute_expires_at');
            $table->text('first_factor');
            $table->text('second_factor')->nullable();
            $table->text('ua_label');
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
        });
        DB::statement('ALTER TABLE user_sessions ADD COLUMN ip_prefix cidr NOT NULL');
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_first_factor_check CHECK (first_factor IN ('password','github','google','passkey','reset_link','recovery_finish'))");
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_second_factor_check CHECK (second_factor IS NULL OR second_factor IN ('security_key','totp','recovery_code','passkey'))");
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_revoked_reason_check CHECK (revoked_reason IS NULL OR revoked_reason IN ('user','signed_out','password_changed','password_reset','recovery','email_changed','admin','expired','account_deleted'))");
        DB::statement('CREATE INDEX user_sessions_live ON user_sessions (user_id) WHERE revoked_at IS NULL');

        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->binary('token_hash')->unique();
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('ended_at')->nullable();
            $table->foreignUuid('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('claimed_at')->nullable();
            $table->integer('stored_captures')->default(0);
            $table->integer('counted_runs')->default(0);
            $table->integer('accepted_runs')->default(0);
            $table->bigInteger('stored_bytes')->default(0);
            $table->binary('claim_nonce_hash')->nullable();
            $table->timestampTz('claim_nonce_expires_at')->nullable();
        });
        DB::statement('ALTER TABLE guest_sessions ADD CONSTRAINT guest_sessions_counters_check CHECK (stored_captures >= 0 AND counted_runs >= 0 AND accepted_runs >= 0)');
    }

    public function down(): void
    {
        foreach (['guest_sessions', 'user_sessions', 'account_deletion_requests', 'email_change_requests',
            'account_recovery_requests', 'second_factor_failures', 'recovery_codes', 'totp_factors',
            'webauthn_credentials', 'user_identities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
