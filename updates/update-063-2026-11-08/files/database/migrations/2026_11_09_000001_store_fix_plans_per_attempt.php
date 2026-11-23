<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One implementation plan per attempt instead of a single replaceable one:
     * fix_plans = { "<attempt the plan is FOR>": { md, from (the attempt it was built from), at } }.
     * The plan that already exists is moved over, not regenerated.
     */
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->longText('fix_plans')->nullable()->after('project_attempts');
        });

        foreach (DB::table('phase_progress')->whereNotNull('fix_plan_md')->get() as $row) {
            $from = max(1, (int) $row->fix_plan_attempt);

            DB::table('phase_progress')->where('id', $row->id)->update([
                'fix_plans' => json_encode(
                    [($from + 1) => ['md' => $row->fix_plan_md, 'from' => $from, 'at' => $row->updated_at ?? now()->toDateTimeString()]],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ]);
        }

        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn(['fix_plan_md', 'fix_plan_attempt']);
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->longText('fix_plan_md')->nullable()->after('project_attempts');
            $table->unsignedSmallInteger('fix_plan_attempt')->nullable()->after('fix_plan_md');
        });

        // Only the newest plan fits the old single-plan columns.
        foreach (DB::table('phase_progress')->whereNotNull('fix_plans')->get() as $row) {
            $plans = json_decode($row->fix_plans, true) ?: [];
            if ($plans === []) {
                continue;
            }
            $newest = $plans[max(array_keys($plans))];

            DB::table('phase_progress')->where('id', $row->id)->update(['fix_plan_md' => $newest['md'], 'fix_plan_attempt' => $newest['from']]);
        }

        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn('fix_plans');
        });
    }
};
