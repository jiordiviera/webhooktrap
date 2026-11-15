<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Workspace ownership (schema.md §3, minimal: the Capture slice adds the
 * project counters and quotas), the audit log (§7) and the app side of the
 * deletion ledger (domain §2.10, DL-3a).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('guest_session_id')->nullable()->unique()->constrained('guest_sessions')->cascadeOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE workspaces ADD CONSTRAINT workspaces_one_owner CHECK ((user_id IS NULL) <> (guest_session_id IS NULL))');

        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->text('project_key')->unique();
            $table->text('name');
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('actor_runner_id')->nullable();
            $table->text('action');
            $table->text('subject')->nullable();
            $table->binary('email_hmac')->nullable();
            $table->foreignUuid('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('ua_label')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('at');
        });
        DB::statement('ALTER TABLE audit_log ADD COLUMN ip_prefix cidr');
        DB::statement('CREATE INDEX audit_log_subject_at ON audit_log (subject_user_id, at DESC)');

        // App side of the deletion ledger (DL-3a). The off-server copy next to
        // the backup repository is A10's (DL-3b/c); see Retention\Application\DeletionLedger.
        Schema::create('deletion_ledger', function (Blueprint $table) {
            $table->text('subject')->primary();
            $table->timestampTz('deleted_at');
            $table->timestampTz('appended_at');
            $table->timestampTz('shipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_ledger');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workspaces');
    }
};
