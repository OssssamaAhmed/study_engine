<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->longText('passing_criteria_md')->nullable()->after('research_context');
        });
    }

    public function down(): void
    {
        Schema::table('phase_progress', function (Blueprint $table) {
            $table->dropColumn('passing_criteria_md');
        });
    }
};
