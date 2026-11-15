<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Agent connection review fixes (project/security/agent-connection-review.md):
 *
 * - AC-3: no silent same-name replacement. A colliding name gets a suffix at
 *   `pair` (`maya-mbp-2`); a replacement happens only when the owner chose it
 *   while issuing the code. The code carries the agent it replaces, the new
 *   agent keeps the link until its confirmation revokes the old one.
 * - AC-8: a waiting decline (`needs_confirmation`, `needs_reconfirm`) holds
 *   the job back from `jobs/next` until `offer_after`.
 * - AC-12: total observation bytes per run, checked against a ceiling.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pairing_codes ADD COLUMN replaces_agent_id uuid NULL REFERENCES agents (id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE agents ADD COLUMN replaces_agent_id uuid NULL REFERENCES agents (id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE agents ADD CONSTRAINT agents_replaces_other CHECK (replaces_agent_id IS NULL OR replaces_agent_id <> id)');
        DB::statement('ALTER TABLE run_jobs ADD COLUMN offer_after timestamptz NULL');
        DB::statement('ALTER TABLE runs ADD COLUMN observation_bytes bigint NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE runs ADD CONSTRAINT runs_observation_bytes CHECK (observation_bytes >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE runs DROP CONSTRAINT runs_observation_bytes');
        DB::statement('ALTER TABLE runs DROP COLUMN observation_bytes');
        DB::statement('ALTER TABLE run_jobs DROP COLUMN offer_after');
        DB::statement('ALTER TABLE agents DROP CONSTRAINT agents_replaces_other');
        DB::statement('ALTER TABLE agents DROP COLUMN replaces_agent_id');
        DB::statement('ALTER TABLE pairing_codes DROP COLUMN replaces_agent_id');
    }
};
