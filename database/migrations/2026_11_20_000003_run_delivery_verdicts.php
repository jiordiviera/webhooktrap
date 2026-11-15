<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * A completed delivery's measured facts stay immutable (domain §6), but the
 * final document still has to give it its verdict: `result`, `phase`
 * (`judged`), the judged probe readings (`state`: `flagged`,
 * `matches_expected`) and its timeline `seq`. Before, the trigger refused
 * every update of a completed row, so a delivery observed live kept
 * `result = null` on a finished run (A07's end-to-end run). The verdict is
 * written once: after `phase = 'judged'`, the row is fully immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION run_deliveries_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.completed AND (OLD.phase IS NOT DISTINCT FROM 'judged'
                    OR ROW(NEW.run_id, NEW.n, NEW.scenario, NEW.capture_id, NEW.source_seq, NEW.fixture_path, NEW.mode, NEW.offset_ms,
                           NEW.signing_kind, NEW.timestamp_offset_s, NEW.tamper, NEW.status, NEW.transport, NEW.latency_ms, NEW.over_budget,
                           NEW.response_content_type, NEW.response_sha256, NEW.response_bytes, NEW.completed)
                       IS DISTINCT FROM
                       ROW(OLD.run_id, OLD.n, OLD.scenario, OLD.capture_id, OLD.source_seq, OLD.fixture_path, OLD.mode, OLD.offset_ms,
                           OLD.signing_kind, OLD.timestamp_offset_s, OLD.tamper, OLD.status, OLD.transport, OLD.latency_ms, OLD.over_budget,
                           OLD.response_content_type, OLD.response_sha256, OLD.response_bytes, OLD.completed)) THEN
                    RAISE EXCEPTION 'a completed run delivery is immutable' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION run_deliveries_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.completed THEN
                    RAISE EXCEPTION 'a completed run delivery is immutable' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$;
            SQL);
    }
};
