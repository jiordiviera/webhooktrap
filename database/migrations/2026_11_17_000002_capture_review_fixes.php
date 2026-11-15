<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Slice 3 review fixes (project/security/slice3-review.md):
 *
 * - S3-7: `captures.counted_bytes`, the bytes a capture counts against the
 *   quotas and the guest budget (kept body + headers + raw target + the other
 *   variable-length request facts). Retention and claim move exactly these
 *   bytes. Existing rows counted their kept body only, so that is the backfill.
 * - S3-13: `capture_bodies` refuses every update; the request-facts trigger
 *   also covers `id` and `counted_bytes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE captures ADD COLUMN counted_bytes bigint NOT NULL DEFAULT 0');
        DB::statement('UPDATE captures SET counted_bytes = CASE WHEN body_kept THEN body_size ELSE 0 END');
        DB::statement('ALTER TABLE captures ALTER COLUMN counted_bytes DROP DEFAULT');
        DB::statement('ALTER TABLE captures ADD CONSTRAINT captures_counted_bytes CHECK (counted_bytes >= 0)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION captures_request_facts_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF (NEW.id, NEW.project_id, NEW.capture_url_id, NEW.seq, NEW.received_at, NEW.method, NEW.raw_target, NEW.path,
                    NEW.raw_query, NEW.headers, NEW.redacted_headers, NEW.header_fidelity, NEW.public_origin,
                    NEW.public_origin_source, NEW.forwarded, NEW.source_ip, NEW.content_type, NEW.body_size,
                    NEW.body_sha256, NEW.body_kept, NEW.counted_bytes)
                   IS DISTINCT FROM
                   (OLD.id, OLD.project_id, OLD.capture_url_id, OLD.seq, OLD.received_at, OLD.method, OLD.raw_target, OLD.path,
                    OLD.raw_query, OLD.headers, OLD.redacted_headers, OLD.header_fidelity, OLD.public_origin,
                    OLD.public_origin_source, OLD.forwarded, OLD.source_ip, OLD.content_type, OLD.body_size,
                    OLD.body_sha256, OLD.body_kept, OLD.counted_bytes) THEN
                    RAISE EXCEPTION 'capture request facts are immutable' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION capture_bodies_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'capture bodies are immutable' USING ERRCODE = 'check_violation';
            END;
            $$;
            CREATE TRIGGER capture_bodies_immutable BEFORE UPDATE ON capture_bodies
                FOR EACH ROW EXECUTE FUNCTION capture_bodies_immutable();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS capture_bodies_immutable ON capture_bodies;
            DROP FUNCTION IF EXISTS capture_bodies_immutable();
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
            SQL);
        DB::statement('ALTER TABLE captures DROP COLUMN counted_bytes');
    }
};
