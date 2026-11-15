<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Agent ↔ server connection (decision 64): the Agent tables (schema.md §5,
 * domain §5) and the server side of runs (schema.md §6, domain §6), minus
 * sharing and the server runner, which come with their slices (the share
 * columns exist so the Sharing slice adds no migration to `runs`).
 *
 * Deviations from schema.md, recorded in contracts.md "Agent connection
 * notes": names and keys are unique among *confirmed* (respectively live)
 * agents, so re-pairing a machine replaces its old agent at confirmation;
 * `agent_auth_nonces` holds the single-use challenge nonces; `runs` keeps
 * `provisional_findings` (jsonb) while Running instead of provisional
 * `findings` rows, because a provisional finding has no fingerprint yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->text('name');
            $table->binary('public_key');
            $table->text('key_fingerprint');
            $table->text('version');
            $table->text('os')->nullable();
            $table->text('arch')->nullable();
            $table->smallInteger('proto');
            $table->text('state')->default('disconnected');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('paired_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->boolean('guest_slot');
            $table->boolean('needs_reconfirm')->default(true);
            $table->timestampTz('confirmed_at')->nullable();
            $table->binary('probe_config_hash')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE agents ADD CONSTRAINT agents_state CHECK (state IN ('connected', 'disconnected', 'revoked'))");
        DB::statement("ALTER TABLE agents ADD CONSTRAINT agents_revoked_reason CHECK (revoked_reason IS NULL OR revoked_reason IN ('user', 'replaced', 'session_ended', 'admin', 'claimed_rekey', 'unconfirmed', 'account_deleted'))");
        DB::statement('ALTER TABLE agents ADD CONSTRAINT agents_revoked CHECK ((revoked_at IS NULL) = (revoked_reason IS NULL))');
        DB::statement("ALTER TABLE agents ADD CONSTRAINT agents_name CHECK (name ~ '^[A-Za-z0-9._-]{1,48}$')");
        DB::statement('ALTER TABLE agents ADD CONSTRAINT agents_public_key CHECK (octet_length(public_key) = 32)');
        DB::statement('ALTER TABLE agents ADD CONSTRAINT agents_probe_hash CHECK (probe_config_hash IS NULL OR octet_length(probe_config_hash) = 32)');
        // RE-1: never confirmed means still waiting for the local [y/N].
        DB::statement('ALTER TABLE agents ADD CONSTRAINT agents_unconfirmed CHECK (confirmed_at IS NOT NULL OR needs_reconfirm)');
        // A-13: one confirmed agent per guest workspace (the previous one is revoked in the confirmation transaction).
        DB::statement('CREATE UNIQUE INDEX agents_one_guest_agent ON agents (workspace_id) WHERE guest_slot AND revoked_at IS NULL AND NOT needs_reconfirm');
        DB::statement('CREATE UNIQUE INDEX agents_confirmed_name ON agents (workspace_id, name) WHERE revoked_at IS NULL AND NOT needs_reconfirm');
        DB::statement('CREATE UNIQUE INDEX agents_live_key ON agents (key_fingerprint) WHERE revoked_at IS NULL');
        DB::statement('CREATE INDEX agents_workspace_live ON agents (workspace_id) WHERE revoked_at IS NULL');
        DB::statement('CREATE INDEX agents_unconfirmed_paired ON agents (paired_at) WHERE confirmed_at IS NULL AND revoked_at IS NULL');

        Schema::create('agent_secret_names', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->text('provider');
            $table->text('env_name');
            $table->primary(['agent_id', 'provider', 'env_name']);
        });
        // S-8, PO-D: names only, never a hint of the value.
        DB::statement("ALTER TABLE agent_secret_names ADD CONSTRAINT agent_secret_names_env CHECK (env_name ~ '^[A-Z_][A-Z0-9_]{0,63}$')");

        Schema::create('agent_targets', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->text('scheme');
            $table->text('host');
            $table->integer('port');
            $table->text('kind');
            $table->boolean('declared');
            $table->timestampTz('approved_at')->nullable();
            $table->primary(['agent_id', 'scheme', 'host', 'port']);
        });
        DB::statement("ALTER TABLE agent_targets ADD CONSTRAINT agent_targets_kind CHECK (kind IN ('local', 'allowed'))");
        DB::statement("ALTER TABLE agent_targets ADD CONSTRAINT agent_targets_scheme CHECK (scheme IN ('http', 'https'))");
        DB::statement('ALTER TABLE agent_targets ADD CONSTRAINT agent_targets_port CHECK (port BETWEEN 1 AND 65535)');

        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->binary('code_hash')->unique();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('guest_session_id')->nullable()->constrained('guest_sessions')->cascadeOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignUuid('used_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->smallInteger('failed_attempts')->default(0);
            $table->timestampTz('created_at');
        });
        DB::statement('ALTER TABLE pairing_codes ADD CONSTRAINT pairing_codes_principal CHECK (num_nonnulls(user_id, guest_session_id) = 1)');
        DB::statement('ALTER TABLE pairing_codes ADD CONSTRAINT pairing_codes_hash CHECK (octet_length(code_hash) = 32)');
        DB::statement('CREATE UNIQUE INDEX pairing_codes_one_live ON pairing_codes (workspace_id) WHERE used_at IS NULL');

        Schema::create('agent_access_tokens', function (Blueprint $table) {
            $table->binary('token_hash');
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->timestampTz('expires_at');
            $table->primary('token_hash');
        });
        DB::statement('CREATE INDEX agent_access_tokens_expiry ON agent_access_tokens (expires_at)');

        // S-3: 32-byte nonces, single use, 60 s, bound to an agent id.
        Schema::create('agent_auth_nonces', function (Blueprint $table) {
            $table->binary('nonce');
            $table->uuid('agent_id');
            $table->timestampTz('expires_at', 3);
            $table->primary('nonce');
        });
        DB::statement('CREATE INDEX agent_auth_nonces_expiry ON agent_auth_nonces (expires_at)');

        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_last_agent FOREIGN KEY (last_agent_id) REFERENCES agents (id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_sig_checked_by_agent FOREIGN KEY (sig_checked_by_agent_id) REFERENCES agents (id) ON DELETE SET NULL');

        Schema::create('runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->integer('number');
            $table->text('source');
            $table->text('executor');
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->uuid('runner_id')->nullable();
            $table->boolean('remote')->default(false);
            $table->jsonb('provenance');
            $table->foreignUuid('initiated_by_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('initiated_by_guest_session_id')->nullable()->constrained('guest_sessions')->cascadeOnDelete();
            $table->uuid('initiated_by_ci_token_id')->nullable();
            $table->text('status');
            $table->text('status_reason')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->text('fail_on')->default('high');
            $table->boolean('fail_on_inconclusive')->default(false);
            $table->text('provider');
            $table->text('pack_id');
            $table->text('pack_version');
            $table->text('target_scheme')->nullable();
            $table->text('target_host')->nullable();
            $table->integer('target_port')->nullable();
            $table->text('target_path')->nullable();
            $table->bigInteger('seed')->nullable();
            $table->binary('plan_hash')->nullable();
            $table->jsonb('plan')->nullable();
            $table->text('probe_display_policy')->nullable();
            $table->smallInteger('label_bits')->nullable();
            $table->jsonb('probe_entries')->nullable();
            $table->smallInteger('counts_critical')->default(0);
            $table->smallInteger('counts_high')->default(0);
            $table->smallInteger('counts_medium')->default(0);
            $table->smallInteger('counts_info')->default(0);
            $table->smallInteger('delivery_count')->default(0);
            $table->boolean('quota_counted')->default(false);
            $table->text('visibility')->default('private');
            $table->binary('share_token_hash')->nullable()->unique();
            $table->timestampTz('share_token_created_at')->nullable();
            $table->text('shared_by_name')->nullable();
            $table->boolean('share_bodies_hidden')->default(false);
            $table->boolean('share_include_probe_values')->default(false);
            $table->timestampTz('shared_at')->nullable();
            $table->integer('last_seq')->default(0);
            $table->jsonb('provisional_findings')->nullable();
            $table->jsonb('document')->nullable();
            $table->binary('document_sha256')->nullable();
            $table->timestampTz('queued_at', 3)->nullable();
            $table->timestampTz('started_at', 3)->nullable();
            $table->timestampTz('finished_at', 3)->nullable();
            $table->uuid('job_id')->nullable();
            $table->timestampTz('job_expires_at')->nullable();
            $table->timestampsTz();
            $table->unique(['project_id', 'number']);
        });
        DB::statement('ALTER TABLE runs ADD COLUMN capture_ids uuid[] NOT NULL');
        DB::statement('ALTER TABLE runs ADD COLUMN source_seqs integer[] NOT NULL');
        // Selection hashes (domain §6: capture refs + SHA-256), hex, same order as capture_ids.
        DB::statement('ALTER TABLE runs ADD COLUMN capture_sha256 text[] NOT NULL');
        DB::statement('ALTER TABLE runs ADD COLUMN scenarios text[] NOT NULL');
        DB::statement("ALTER TABLE runs ADD COLUMN levels smallint[] NOT NULL DEFAULT '{1}'");
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_source CHECK (source IN ('web', 'cli', 'ci'))");
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_executor CHECK (executor IN ('agent', 'server_public', 'server_allowlist'))");
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_status CHECK (status IN ('queued', 'running', 'couldnt_start', 'aborted', 'findings', 'inconclusive', 'passed'))");
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_exit_code CHECK (exit_code IS NULL OR exit_code IN (0, 1, 2, 3))');
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_fail_on CHECK (fail_on IN ('critical', 'high', 'medium', 'never'))");
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_probe_policy CHECK (probe_display_policy IS NULL OR probe_display_policy IN ('strict', 'opt_in_allowed', 'opt_in_disabled'))");
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_label_bits CHECK (label_bits IS NULL OR label_bits IN (24, 48))');
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_delivery_count CHECK (delivery_count BETWEEN 0 AND 50)');
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_visibility CHECK (visibility IN ('private', 'link'))");
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_share_token CHECK ((visibility = 'link') = (share_token_hash IS NOT NULL))");
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_initiator CHECK (num_nonnulls(initiated_by_user_id, initiated_by_guest_session_id, initiated_by_ci_token_id) = 1)');
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_one_executor CHECK (num_nonnulls(agent_id, runner_id) <= 1)');
        DB::statement("ALTER TABLE runs ADD CONSTRAINT runs_server_level1 CHECK (executor = 'agent' OR (levels = '{1}' AND probe_entries IS NULL AND label_bits IS NULL))");
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_document_size CHECK (document IS NULL OR pg_column_size(document) <= 2097152)');
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_document_hash CHECK ((document IS NULL) = (document_sha256 IS NULL))');
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_selection CHECK (cardinality(capture_ids) BETWEEN 1 AND 5)');
        DB::statement('CREATE INDEX runs_project_number ON runs (project_id, number DESC)');
        DB::statement('CREATE INDEX runs_project_source_status ON runs (project_id, source, status)');
        DB::statement('CREATE INDEX runs_finished_at ON runs (finished_at)');
        DB::statement("CREATE INDEX runs_agent_open ON runs (agent_id) WHERE status IN ('queued', 'running')");

        Schema::create('run_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->nullable()->constrained('runs')->cascadeOnDelete();
            $table->text('kind');
            $table->foreignUuid('agent_id')->nullable()->constrained('agents')->cascadeOnDelete();
            $table->uuid('runner_id')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('secrets_fetched_at')->nullable();
            $table->timestampTz('created_at');
        });
        DB::statement('ALTER TABLE run_jobs ADD COLUMN capture_ids uuid[] NOT NULL');
        DB::statement("ALTER TABLE run_jobs ADD CONSTRAINT run_jobs_kind CHECK (kind IN ('run', 'verify'))");
        DB::statement("ALTER TABLE run_jobs ADD CONSTRAINT run_jobs_verify_agent CHECK (kind <> 'verify' OR agent_id IS NOT NULL)");
        DB::statement("ALTER TABLE run_jobs ADD CONSTRAINT run_jobs_run CHECK ((kind = 'run') = (run_id IS NOT NULL))");
        DB::statement('ALTER TABLE run_jobs ADD CONSTRAINT run_jobs_secrets CHECK (secrets_fetched_at IS NULL OR runner_id IS NOT NULL)');
        DB::statement('CREATE INDEX run_jobs_agent_open ON run_jobs (agent_id, created_at) WHERE completed_at IS NULL');
        DB::statement('CREATE INDEX run_jobs_capture_ids ON run_jobs USING GIN (capture_ids) WHERE completed_at IS NULL');

        // TM-35: every capture fetch by an executor, append-only, kept with the capture.
        Schema::create('capture_access_log', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('capture_id')->constrained('captures')->cascadeOnDelete();
            $table->uuid('job_id');
            $table->uuid('agent_id')->nullable();
            $table->uuid('runner_id')->nullable();
            $table->timestampTz('at');
        });
        DB::statement('ALTER TABLE capture_access_log ADD CONSTRAINT capture_access_log_executor CHECK (num_nonnulls(agent_id, runner_id) = 1)');
        DB::statement('CREATE INDEX capture_access_log_capture ON capture_access_log (capture_id, at)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION capture_access_log_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'capture_access_log is append-only' USING ERRCODE = 'check_violation';
            END;
            $$;
            CREATE TRIGGER capture_access_log_append_only BEFORE UPDATE ON capture_access_log
                FOR EACH ROW EXECUTE FUNCTION capture_access_log_append_only();
            SQL);

        Schema::create('run_events_inbox', function (Blueprint $table) {
            $table->foreignUuid('run_id')->constrained('runs')->cascadeOnDelete();
            $table->integer('seq');
            $table->timestampTz('received_at');
            $table->primary(['run_id', 'seq']);
        });

        Schema::create('run_scenarios', function (Blueprint $table) {
            $table->foreignUuid('run_id')->constrained('runs')->cascadeOnDelete();
            $table->smallInteger('position');
            $table->text('name');
            $table->text('result')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('reading')->nullable();
            $table->primary(['run_id', 'position']);
        });
        DB::statement("ALTER TABLE run_scenarios ADD CONSTRAINT run_scenarios_result CHECK (result IS NULL OR result IN ('pass', 'finding', 'inconclusive', 'unreachable', 'n/a', 'not_run'))");
        DB::statement('CREATE UNIQUE INDEX run_scenarios_name ON run_scenarios (run_id, name)');

        Schema::create('run_deliveries', function (Blueprint $table) {
            $table->foreignUuid('run_id')->constrained('runs')->cascadeOnDelete();
            $table->smallInteger('n');
            $table->text('scenario');
            $table->foreignUuid('capture_id')->nullable()->constrained('captures')->nullOnDelete();
            $table->integer('source_seq')->nullable();
            $table->text('fixture_path')->nullable();
            $table->text('mode');
            $table->integer('offset_ms')->nullable();
            $table->text('signing_kind');
            $table->integer('timestamp_offset_s')->nullable();
            $table->jsonb('tamper')->nullable();
            $table->smallInteger('status')->nullable();
            $table->text('transport')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('over_budget')->nullable();
            $table->text('response_content_type')->nullable();
            $table->binary('response_sha256')->nullable();
            $table->integer('response_bytes')->nullable();
            $table->jsonb('state')->nullable();
            $table->text('result')->nullable();
            $table->text('phase')->nullable();
            $table->boolean('completed');
            $table->integer('seq');
            $table->primary(['run_id', 'n']);
        });
        DB::statement("ALTER TABLE run_deliveries ADD CONSTRAINT run_deliveries_mode CHECK (mode IN ('normal', 'duplicate', 'retry', 'late', 'tampered', 'unsigned'))");
        DB::statement("ALTER TABLE run_deliveries ADD CONSTRAINT run_deliveries_signing CHECK (signing_kind IN ('resigned', 'original', 'original_body_fresh_ts', 'none'))");
        DB::statement("ALTER TABLE run_deliveries ADD CONSTRAINT run_deliveries_transport CHECK (transport IS NULL OR transport IN ('timeout', 'reset', 'refused', 'dns', 'tls'))");
        DB::statement("ALTER TABLE run_deliveries ADD CONSTRAINT run_deliveries_result CHECK (result IS NULL OR result IN ('pass', 'finding', 'inconclusive', 'unreachable', 'sent_no_result', 'not_run'))");
        DB::statement('ALTER TABLE run_deliveries ADD CONSTRAINT run_deliveries_n CHECK (n BETWEEN 1 AND 50)');
        // Written once when completed; immutable afterwards (domain §6). The
        // PV-4 label widening (readings.replace) is the one later change to
        // `state`, and comes with its endpoint.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION run_deliveries_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.completed THEN
                    RAISE EXCEPTION 'a completed run delivery is immutable' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER run_deliveries_immutable BEFORE UPDATE ON run_deliveries
                FOR EACH ROW EXECUTE FUNCTION run_deliveries_immutable();
            SQL);

        Schema::create('findings', function (Blueprint $table) {
            $table->foreignUuid('run_id')->constrained('runs')->cascadeOnDelete();
            $table->smallInteger('n');
            $table->uuid('project_id');
            $table->text('type');
            $table->char('fingerprint', 12);
            $table->text('severity');
            $table->smallInteger('level');
            $table->text('event_type')->nullable();
            $table->jsonb('evidence');
            $table->text('pack_version');
            $table->jsonb('params');
            $table->text('framework')->nullable();
            $table->text('likely_cause_rule')->nullable();
            $table->jsonb('measured')->nullable();
            $table->primary(['run_id', 'n']);
        });
        DB::statement("ALTER TABLE findings ADD COLUMN scenarios text[] NOT NULL DEFAULT '{}'");
        DB::statement("ALTER TABLE findings ADD CONSTRAINT findings_type CHECK (type IN ('accepts_tampered', 'accepts_unsigned', 'accepts_stale', 'duplicate_changed_state', 'order_changed_state', 'concurrent_changed_state', 'partial_side_effect', 'retry_diverged', 'server_error', 'non_2xx_on_valid', 'error_on_invalid', 'over_budget', 'needs_probe', 'order_dependent', 'probe_gap', 'cold_start', 'async_write'))");
        DB::statement("ALTER TABLE findings ADD CONSTRAINT findings_severity CHECK (severity IN ('critical', 'high', 'medium', 'info'))");
        DB::statement('ALTER TABLE findings ADD CONSTRAINT findings_level CHECK (level IN (1, 2))');
        DB::statement("ALTER TABLE findings ADD CONSTRAINT findings_fingerprint CHECK (fingerprint ~ '^[0-9a-f]{12}$')");
        DB::statement('CREATE INDEX findings_fingerprint_lookup ON findings (project_id, fingerprint, run_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_sig_checked_by_agent');
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_last_agent');
        foreach (['findings', 'run_deliveries', 'run_scenarios', 'run_events_inbox', 'capture_access_log', 'run_jobs', 'runs',
            'agent_auth_nonces', 'agent_access_tokens', 'pairing_codes', 'agent_targets', 'agent_secret_names', 'agents'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS run_deliveries_immutable(); DROP FUNCTION IF EXISTS capture_access_log_append_only();');
    }
};
