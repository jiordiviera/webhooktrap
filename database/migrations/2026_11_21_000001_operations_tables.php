<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Operations tables for the first deploy (C6):
 * - `instance_heartbeats`: the scheduler's heartbeat, read by `app:heartbeat-check`
 *   (compose-profile §6) and `/health`;
 * - `store_failure_minutes`: per-minute counts of fail-open store failures behind
 *   `wt.store_failure`, kept in the database so `/health` sees the counts of every
 *   container while Valkey is the store that fails (hosted plan §6.8);
 * - `runners`: runner tokens (schema §5 `runners`, RN-1, RN-2), issued by `runner:token`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instance_heartbeats', function (Blueprint $table) {
            $table->text('name')->primary();
            $table->timestampTz('beat_at');
        });

        Schema::create('store_failure_minutes', function (Blueprint $table) {
            $table->text('kind');
            $table->timestampTz('minute');
            $table->integer('failures');
            $table->primary(['kind', 'minute']);
        });

        Schema::create('runners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('name')->unique();
            $table->binary('token_hash')->unique();
            $table->char('last4', 4);
            $table->boolean('binding_required')->default(true);
            $table->timestampTz('issued_at');
            $table->text('version')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
        });
        DB::statement('ALTER TABLE runners ADD COLUMN allowed_cidrs cidr[]');
        DB::statement('ALTER TABLE runners ADD CONSTRAINT runners_binding CHECK (NOT binding_required OR (allowed_cidrs IS NOT NULL AND cardinality(allowed_cidrs) > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('runners');
        Schema::dropIfExists('store_failure_minutes');
        Schema::dropIfExists('instance_heartbeats');
    }
};
