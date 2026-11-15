<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Slice 2, workspace and project ownership (schema.md §3, domain §3): project
 * settings, counters and the account capture quota columns, and the guest
 * invariant "exactly one project per guest workspace" in the database.
 *
 * `last_agent_id` has no foreign key yet: the Agent slice adds
 * `REFERENCES agents ON DELETE SET NULL` when `agents` exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->smallInteger('capture_retention_days')->default(30);
            $table->smallInteger('run_retention_days')->default(90);
            $table->text('default_visibility')->default('private');
            $table->integer('captures_today')->default(0);
            $table->date('captures_day')->nullable();
            $table->bigInteger('stored_body_bytes')->default(0);
            $table->integer('next_capture_seq')->default(1);
            $table->integer('next_run_number')->default(1);
            $table->uuid('last_agent_id')->nullable();
            // Copied from the workspace by the trigger below; never set by the app.
            $table->boolean('is_guest')->default(false);
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_default_visibility CHECK (default_visibility IN ('private', 'link'))");
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_retention_range CHECK (capture_retention_days BETWEEN 1 AND 365 AND run_retention_days BETWEEN 1 AND 365)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_counters_positive CHECK (next_capture_seq >= 1 AND next_run_number >= 1 AND captures_today >= 0 AND stored_body_bytes >= 0)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_name_length CHECK (char_length(name) BETWEEN 1 AND 80)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_key_format CHECK (project_key ~ \'^[A-Za-z0-9]{10}$\')');

        // `is_guest` follows the owning workspace on insert and whenever the
        // project moves (a claim moves the guest project into the user's
        // workspace, which makes it an ordinary account project).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION projects_set_is_guest() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                NEW.is_guest := EXISTS (
                    SELECT 1 FROM workspaces WHERE id = NEW.workspace_id AND guest_session_id IS NOT NULL
                );
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER projects_is_guest BEFORE INSERT OR UPDATE OF workspace_id, is_guest ON projects
                FOR EACH ROW EXECUTE FUNCTION projects_set_is_guest();
            SQL);
        DB::statement('UPDATE projects SET workspace_id = workspace_id');

        // Guest invariant (domain §3): one project per guest workspace.
        DB::statement('CREATE UNIQUE INDEX projects_one_per_guest_workspace ON projects (workspace_id) WHERE is_guest');
        DB::statement('CREATE INDEX projects_workspace_live ON projects (workspace_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX projects_soft_deleted ON projects (deleted_at) WHERE deleted_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS projects_soft_deleted');
        DB::statement('DROP INDEX IF EXISTS projects_workspace_live');
        DB::statement('DROP INDEX IF EXISTS projects_one_per_guest_workspace');
        DB::unprepared('DROP TRIGGER IF EXISTS projects_is_guest ON projects; DROP FUNCTION IF EXISTS projects_set_is_guest();');
        foreach (['projects_key_format', 'projects_name_length', 'projects_counters_positive', 'projects_retention_range', 'projects_default_visibility'] as $constraint) {
            DB::statement("ALTER TABLE projects DROP CONSTRAINT IF EXISTS {$constraint}");
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'capture_retention_days', 'run_retention_days', 'default_visibility', 'captures_today', 'captures_day',
                'stored_body_bytes', 'next_capture_seq', 'next_run_number', 'last_agent_id', 'is_guest',
            ]);
        });
    }
};
