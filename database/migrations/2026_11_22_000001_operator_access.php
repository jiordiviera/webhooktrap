<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Operator admin panel (decision 74):
 *
 * - `users.is_operator`, set only by `operator:grant` / `operator:revoke`
 *   (never mass assignable, never reachable from HTTP);
 * - `operator_handoff` as a first factor: the admin-host session starts from
 *   a single-use handoff minted by a recently confirmed app session.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN is_operator boolean NOT NULL DEFAULT false');
        DB::statement('ALTER TABLE user_sessions DROP CONSTRAINT user_sessions_first_factor_check');
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_first_factor_check CHECK (first_factor IN ('password','github','google','passkey','reset_link','recovery_finish','operator_handoff'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM user_sessions WHERE first_factor = 'operator_handoff'");
        DB::statement('ALTER TABLE user_sessions DROP CONSTRAINT user_sessions_first_factor_check');
        DB::statement("ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_first_factor_check CHECK (first_factor IN ('password','github','google','passkey','reset_link','recovery_finish'))");
        DB::statement('ALTER TABLE users DROP COLUMN is_operator');
    }
};
