<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->longText('implementation_plan_md')->nullable()->after('research_context');
            $table->longText('skeleton_md')->nullable()->after('implementation_plan_md');
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn(['implementation_plan_md', 'skeleton_md']);
        });
    }
};
