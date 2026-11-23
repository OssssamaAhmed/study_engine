<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            // One entry per graded submission (score, feedback, defects) so a resubmission knows what was wrong before.
            $table->longText('project_attempts')->nullable()->after('project_score');
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn('project_attempts');
        });
    }
};
