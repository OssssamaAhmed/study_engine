<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            // The implementation plan for the NEXT attempt, written from the last evaluation's weak points.
            $table->longText('fix_plan_md')->nullable()->after('project_attempts');
            // The attempt whose evaluation this plan was built from (the plan is stale once a later attempt is graded).
            $table->unsignedSmallInteger('fix_plan_attempt')->nullable()->after('fix_plan_md');
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn(['fix_plan_md', 'fix_plan_attempt']);
        });
    }
};
