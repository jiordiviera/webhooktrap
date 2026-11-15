<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Identity: users (schema.md §2). Ids are UUIDv7 generated in PHP
 * (App\Infrastructure\Ids), emails are citext, hashes are argon2id.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('name')->nullable();
            $table->boolean('is_instance_admin')->default(false);
            $table->boolean('can_private_replay')->default(false);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampTz('invited_at')->nullable();
            $table->text('password_hash')->nullable();
            $table->binary('webauthn_user_handle')->unique();
            $table->timestampTz('two_factor_enabled_at')->nullable();
            $table->text('legacy_source')->nullable();
            $table->timestampTz('legacy_imported_at')->nullable();
            $table->timestampTz('legacy_activated_at')->nullable();
            $table->timestampTz('second_factor_hold_until')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE users ADD COLUMN email citext NOT NULL');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_legacy_source_check CHECK (legacy_source IS NULL OR legacy_source IN ('1x'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_legacy_password_check CHECK (legacy_source IS NULL OR password_hash IS NULL OR legacy_activated_at IS NOT NULL)');

        // Laravel's reset table, keyed by the token hash: the emailed URL is
        // /reset-password/{token} with no email parameter (domain §2.9).
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('token')->unique();
            $table->timestampTz('created_at');
        });
        DB::statement('ALTER TABLE password_reset_tokens ADD COLUMN email citext PRIMARY KEY');

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
