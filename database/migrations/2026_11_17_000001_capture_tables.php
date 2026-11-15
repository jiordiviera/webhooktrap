<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Slice 3, Capture (schema.md §4, domain §4, ADR-005 amendment 1): capture
 * URLs, captures with their immutable request facts, derived-fact and
 * signature-verdict columns (filled by later slices), raw bodies in their own
 * table, and the global guest storage budget counter (`instance_counters`,
 * schema §10, TM-02).
 *
 * `sig_checked_by_agent_id` has no foreign key yet: the Agent slice adds
 * `REFERENCES agents ON DELETE SET NULL` when `agents` exists.
 */
return new class extends Migration
{
    private const string KEY_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function up(): void
    {
        Schema::create('capture_urls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->text('capture_key')->unique();
            $table->text('state');
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE capture_urls ADD CONSTRAINT capture_urls_state CHECK (state IN ('active', 'rotated'))");
        DB::statement("ALTER TABLE capture_urls ADD CONSTRAINT capture_urls_key_format CHECK (capture_key ~ '^[A-Za-z0-9]{12}$')");
        DB::statement("ALTER TABLE capture_urls ADD CONSTRAINT capture_urls_rotated_at CHECK ((state = 'rotated') = (rotated_at IS NOT NULL))");
        // P9: one active capture URL per project.
        DB::statement("CREATE UNIQUE INDEX capture_urls_one_active ON capture_urls (project_id) WHERE state = 'active'");

        Schema::create('captures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('capture_url_id')->constrained('capture_urls')->cascadeOnDelete();
            $table->integer('seq');
            $table->timestampTz('received_at', 6);
            // Request facts: immutable after insert (trigger below).
            $table->text('method');
            $table->text('raw_target');
            $table->text('path');
            $table->text('raw_query')->nullable();
            $table->jsonb('headers');
            $table->text('header_fidelity');
            $table->text('public_origin');
            $table->text('public_origin_source');
            $table->jsonb('forwarded')->nullable();
            $table->text('content_type')->nullable();
            $table->bigInteger('body_size');
            $table->binary('body_sha256');
            $table->boolean('body_kept');
            // Derived facts (derivation job, Packs slice).
            $table->text('provider')->nullable();
            $table->text('provider_event_id')->nullable();
            $table->text('event_type')->nullable();
            $table->text('api_version')->nullable();
            $table->text('group_key')->nullable();
            $table->jsonb('shape')->nullable();
            $table->text('shape_verdict')->nullable();
            $table->text('derived_with_pack')->nullable();
            // Signature verdict (agent verify jobs, TM-36; never computed by a hosted server).
            $table->text('sig_state')->nullable();
            $table->text('sig_reason')->nullable();
            $table->jsonb('sig_checks')->nullable();
            $table->uuid('sig_checked_by_agent_id')->nullable();
            $table->uuid('sig_checked_by_job_id')->nullable();
            $table->timestampTz('sig_checked_at')->nullable();
            $table->unique(['project_id', 'seq']);
        });
        DB::statement("ALTER TABLE captures ADD COLUMN redacted_headers text[] NOT NULL DEFAULT '{}'");
        DB::statement('ALTER TABLE captures ADD COLUMN source_ip inet NOT NULL');
        DB::statement('ALTER TABLE captures ADD COLUMN object_ids text[]');
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_seq_positive CHECK (seq >= 1)');
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_method_token CHECK (char_length(method) BETWEEN 1 AND 32)');
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_header_fidelity CHECK (header_fidelity IN ('exact', 'sapi'))");
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_public_origin_source CHECK (public_origin_source IN ('configured', 'forwarded_trusted', 'direct'))");
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_body_size CHECK (body_size >= 0)');
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_body_sha256 CHECK (octet_length(body_sha256) = 32)');
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_shape_verdict CHECK (shape_verdict IS NULL OR shape_verdict IN ('new', 'matches', 'differs'))");
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_sig_state CHECK (sig_state IS NULL OR sig_state IN ('unsigned', 'unknown_scheme', 'invalid', 'not_checked', 'no_secret', 'valid'))");
        DB::statement('CREATE INDEX captures_project_seq ON captures (project_id, seq DESC)');
        DB::statement('CREATE INDEX captures_project_group ON captures (project_id, group_key, seq DESC)');
        DB::statement('CREATE INDEX captures_project_event_type ON captures (project_id, event_type)');
        DB::statement('CREATE INDEX captures_object_ids ON captures USING GIN (object_ids)');
        DB::statement('CREATE INDEX captures_received_at ON captures (received_at)');
        DB::statement('CREATE INDEX captures_shape_baseline ON captures (project_id, provider, event_type, api_version)');

        // Domain §4: request facts never change after insert; only derived
        // facts and the signature verdict do.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION captures_request_facts_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (NEW.project_id, NEW.capture_url_id, NEW.seq, NEW.received_at, NEW.method, NEW.raw_target, NEW.path,
                    NEW.raw_query, NEW.headers, NEW.redacted_headers, NEW.header_fidelity, NEW.public_origin,
                    NEW.public_origin_source, NEW.forwarded, NEW.source_ip, NEW.content_type, NEW.body_size,
                    NEW.body_sha256, NEW.body_kept)
                   IS DISTINCT FROM
                   (OLD.project_id, OLD.capture_url_id, OLD.seq, OLD.received_at, OLD.method, OLD.raw_target, OLD.path,
                    OLD.raw_query, OLD.headers, OLD.redacted_headers, OLD.header_fidelity, OLD.public_origin,
                    OLD.public_origin_source, OLD.forwarded, OLD.source_ip, OLD.content_type, OLD.body_size,
                    OLD.body_sha256, OLD.body_kept) THEN
                    RAISE EXCEPTION 'capture request facts are immutable' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER captures_request_facts_immutable BEFORE UPDATE ON captures
                FOR EACH ROW EXECUTE FUNCTION captures_request_facts_immutable();
            SQL);

        Schema::create('capture_bodies', function (Blueprint $table) {
            $table->foreignUuid('capture_id')->primary()->constrained('captures')->cascadeOnDelete();
            $table->binary('body');
        });
        DB::statement('ALTER TABLE capture_bodies ADD CONSTRAINT capture_bodies_kept_limit CHECK (octet_length(body) <= 5242880)');

        // Global guest storage budget (TM-02): one row, guarded update in the
        // ingest transaction. Bytes come back when a guest session stops being
        // live (ended, claimed) or its row is deleted, so the counter tracks
        // the bytes of live guest sessions only.
        Schema::create('instance_counters', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->bigInteger('guest_stored_bytes')->default(0);
        });
        DB::statement('ALTER TABLE instance_counters ADD CONSTRAINT instance_counters_single_row CHECK (id = 1)');
        DB::statement('ALTER TABLE instance_counters ADD CONSTRAINT instance_counters_positive CHECK (guest_stored_bytes >= 0)');
        DB::table('instance_counters')->insert(['id' => 1, 'guest_stored_bytes' => 0]);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guest_sessions_release_budget() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.ended_at IS NULL AND OLD.claimed_at IS NULL AND OLD.stored_bytes > 0 THEN
                        UPDATE instance_counters SET guest_stored_bytes = GREATEST(guest_stored_bytes - OLD.stored_bytes, 0) WHERE id = 1;
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.ended_at IS NULL AND OLD.claimed_at IS NULL
                   AND (NEW.ended_at IS NOT NULL OR NEW.claimed_at IS NOT NULL) AND NEW.stored_bytes > 0 THEN
                    UPDATE instance_counters SET guest_stored_bytes = GREATEST(guest_stored_bytes - NEW.stored_bytes, 0) WHERE id = 1;
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER guest_sessions_release_budget AFTER UPDATE OF ended_at, claimed_at OR DELETE ON guest_sessions
                FOR EACH ROW EXECUTE FUNCTION guest_sessions_release_budget();
            SQL);

        // Projects created before this slice get their capture URL now.
        $now = DB::selectOne('SELECT now() AS now')->now;
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            DB::table('capture_urls')->insert([
                'id' => DB::selectOne('SELECT uuidv7() AS id')->id,
                'project_id' => $projectId,
                'capture_key' => $this->unusedKey(),
                'state' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS guest_sessions_release_budget ON guest_sessions; DROP FUNCTION IF EXISTS guest_sessions_release_budget();');
        Schema::dropIfExists('instance_counters');
        Schema::dropIfExists('capture_bodies');
        DB::unprepared('DROP TRIGGER IF EXISTS captures_request_facts_immutable ON captures; DROP FUNCTION IF EXISTS captures_request_facts_immutable();');
        Schema::dropIfExists('captures');
        Schema::dropIfExists('capture_urls');
    }

    private function unusedKey(): string
    {
        do {
            $key = '';
            for ($i = 0; $i < 12; $i++) {
                $key .= self::KEY_ALPHABET[random_int(0, 61)];
            }
        } while (DB::table('capture_urls')->where('capture_key', $key)->exists());

        return $key;
    }
};
