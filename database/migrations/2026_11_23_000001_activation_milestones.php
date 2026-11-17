<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activation funnel (issue #18, decision 79). Each project remembers when it
 * first received a delivery and first got a verdict, so each milestone is
 * counted once. The counts themselves go to `activation_daily`: one row per
 * UTC day, milestone and guest flag, with no project, user, session or
 * network identifier. They outlive the projects they came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestampTz('first_capture_at')->nullable();
            $table->timestampTz('first_run_at')->nullable();
        });

        Schema::create('activation_daily', function (Blueprint $table) {
            $table->date('day');
            $table->text('milestone');
            $table->boolean('guest');
            $table->integer('count')->default(0);
            // Seconds from project creation, summed, for the average time to the milestone.
            $table->bigInteger('seconds_total')->default(0);
            $table->primary(['day', 'milestone', 'guest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_daily');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['first_capture_at', 'first_run_at']);
        });
    }
};
