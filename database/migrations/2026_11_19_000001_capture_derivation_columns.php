<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Packs slice: derived facts the stream shows beside the slice 3 columns.
 *
 * - `provider_name`: display name of the provider, or of the sender on a
 *   scheme pack (`Parcelbird` on Standard Webhooks, from its User-Agent).
 * - `group_label`: GRP-3 label of the group (`Order #1042`, `sub_1QH…`).
 * - `group_anchor`: object type of the group's anchor (`subscription`), so a
 *   later capture can compare anchor priority when groups merge (GRP-2/3).
 *
 * All three are derived facts: the request-facts trigger does not cover them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE captures ADD COLUMN provider_name text, ADD COLUMN group_label text, ADD COLUMN group_anchor text');
        // GRP-2 lookups go by (project, provider) before the GIN overlap on object_ids.
        DB::statement('CREATE INDEX captures_project_provider ON captures (project_id, provider)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS captures_project_provider');
        DB::statement('ALTER TABLE captures DROP COLUMN provider_name, DROP COLUMN group_label, DROP COLUMN group_anchor');
    }
};
